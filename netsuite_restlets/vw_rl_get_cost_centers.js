/**
 * vw_rl_get_cost_centers.js
 *
 * NetSuite RESTlet that returns a paginated list of Cost Centers for the
 * Truefoods/Sanifu Laravel integrator. In Truefoods "Cost Center" is the
 * standard Class segment renamed, so this reads the classification record.
 * The returned costCenterId is the value to send as "classId" on
 * sales orders and estimates.
 *
 * Script ID:  customscript_vw_rl_get_cost_centers
 * Deploy ID:  customdeploy_vw_rl_get_cost_centers
 *
 * Method: GET
 * Endpoint (Laravel): POST /api/truefoods-sanifu/get/cost-centers
 *
 * Supported query parameters:
 *   page              // Page number (default 1)
 *   pageSize          // Records per page (default 50, max 1000)
 *   costCenterId      // Filter by cost center (class) internal ID
 *   name              // Filter by name / full name (contains, case-insensitive)
 *   subsidiary        // Filter by subsidiary internal ID (cost centers available to that subsidiary)
 *   parent            // Filter by parent cost center internal ID
 *   isInactive        // true = only inactive, false/omitted = only active
 *   includeInactive   // true = return both active and inactive (overrides isInactive)
 *
 * Success response:
 * {
 *   "success": true,
 *   "data": [
 *     {
 *       "costCenterId":    2,
 *       "name":            "Production",
 *       "fullName":        "Operations : Production",
 *       "parent":          1,
 *       "parentName":      "Operations",
 *       "subsidiary":      "1, 2",
 *       "includeChildren": false,
 *       "isInactive":      false,
 *       "lastModifiedDate": "06/23/2026"
 *     }
 *   ],
 *   "pagination": {
 *     "page": 1, "pageSize": 50, "totalRecords": 12, "totalPages": 1,
 *     "currentPageCount": 12, "startItem": 1, "endItem": 12,
 *     "hasNextPage": false, "hasPreviousPage": false
 *   }
 * }
 *
 * Error response:
 * {
 *   "success": false,
 *   "error": {
 *     "type":    "ERROR_CODE",
 *     "message": "Human-readable error message",
 *     "details": <optional native error object>
 *   }
 * }
 *
 * @NApiVersion 2.1
 * @NScriptType Restlet
 * @NModuleScope Public
 */
define(['N/query', 'N/log'], function (query, log) {

    var DEFAULT_PAGE_SIZE = 50;
    var MAX_PAGE_SIZE = 1000;

    var SELECT_COLUMNS =
        'c.id AS costcenterid, ' +
        'c.name, ' +
        'c.fullname, ' +
        'c.parent, ' +
        'BUILTIN.DF(c.parent) AS parentname, ' +
        'c.subsidiary, ' +
        'c.includechildren, ' +
        'c.isinactive, ' +
        'c.lastmodifieddate';

    var FROM_CLAUSE = 'FROM classification c';

    /**
     * Returns true when a request parameter should be treated as boolean true.
     */
    function isTrue(value) {
        return value === true || value === 'true' || value === 'T' || value === '1' || value === 1;
    }

    /**
     * Returns true when a request parameter was explicitly supplied.
     */
    function isProvided(value) {
        return value !== null && value !== undefined && value !== '';
    }

    /**
     * Converts a NetSuite 'T'/'F' flag to a real boolean.
     */
    function toBool(flag) {
        return flag === 'T' || flag === true;
    }

    /**
     * Converts an internal ID column to a number (or null when empty).
     */
    function toId(value) {
        if (!isProvided(value)) {
            return null;
        }
        var parsed = parseInt(value, 10);
        return isNaN(parsed) ? null : parsed;
    }

    /**
     * Builds the WHERE clause and bind parameters from the request filters.
     *
     * @param {Object} params - raw RESTlet query parameters
     * @returns {{clause: string, values: Array}}
     */
    function buildWhere(params) {
        var conditions = [];
        var values = [];

        if (isProvided(params.costCenterId)) {
            conditions.push('c.id = ?');
            values.push(parseInt(params.costCenterId, 10));
        }

        if (isProvided(params.name)) {
            conditions.push('(UPPER(c.name) LIKE ? OR UPPER(c.fullname) LIKE ?)');
            var nameFilter = '%' + String(params.name).toUpperCase() + '%';
            values.push(nameFilter);
            values.push(nameFilter);
        }

        if (isProvided(params.subsidiary)) {
            // Cost Centers can be shared across subsidiaries, so match on any assignment.
            conditions.push('EXISTS (SELECT 1 FROM classificationsubsidiarymap m WHERE m.classification = c.id AND m.subsidiary = ?)');
            values.push(parseInt(params.subsidiary, 10));
        }

        if (isProvided(params.parent)) {
            conditions.push('c.parent = ?');
            values.push(parseInt(params.parent, 10));
        }

        // Inactive handling: by default only active cost centers are returned.
        if (!isTrue(params.includeInactive)) {
            conditions.push('c.isinactive = ?');
            values.push(isTrue(params.isInactive) ? 'T' : 'F');
        }

        return {
            clause: conditions.length ? ' WHERE ' + conditions.join(' AND ') : '',
            values: values
        };
    }

    /**
     * Maps a SuiteQL row to the API response shape.
     */
    function mapCostCenter(row) {
        return {
            costCenterId: toId(row.costcenterid),
            name: row.name || null,
            fullName: row.fullname || null,
            parent: toId(row.parent),
            parentName: row.parentname || null,
            subsidiary: isProvided(row.subsidiary) ? String(row.subsidiary) : null,
            includeChildren: toBool(row.includechildren),
            isInactive: toBool(row.isinactive),
            lastModifiedDate: row.lastmodifieddate || null
        };
    }

    /**
     * GET handler.
     *
     * @param {Object} requestParams
     * @returns {Object}
     */
    function get(requestParams) {
        try {
            var params = requestParams || {};

            var page = parseInt(params.page, 10);
            if (isNaN(page) || page < 1) {
                page = 1;
            }

            var pageSize = parseInt(params.pageSize, 10);
            if (isNaN(pageSize) || pageSize < 1) {
                pageSize = DEFAULT_PAGE_SIZE;
            }
            if (pageSize > MAX_PAGE_SIZE) {
                pageSize = MAX_PAGE_SIZE;
            }

            var where = buildWhere(params);

            // Total record count for the same filter set.
            var countRows = query.runSuiteQL({
                query: 'SELECT COUNT(*) AS total ' + FROM_CLAUSE + where.clause,
                params: where.values
            }).asMappedResults();

            var totalRecords = countRows.length ? parseInt(countRows[0].total, 10) : 0;
            var totalPages = totalRecords ? Math.ceil(totalRecords / pageSize) : 0;
            var offset = (page - 1) * pageSize;

            var rows = [];
            if (offset < totalRecords) {
                // OFFSET/FETCH values are validated integers, so they are inlined.
                rows = query.runSuiteQL({
                    query: 'SELECT ' + SELECT_COLUMNS + ' ' + FROM_CLAUSE + where.clause +
                        ' ORDER BY c.fullname, c.id' +
                        ' OFFSET ' + offset + ' ROWS FETCH NEXT ' + pageSize + ' ROWS ONLY',
                    params: where.values
                }).asMappedResults();
            }

            var data = rows.map(mapCostCenter);

            return {
                success: true,
                data: data,
                pagination: {
                    page: page,
                    pageSize: pageSize,
                    totalRecords: totalRecords,
                    totalPages: totalPages,
                    currentPageCount: data.length,
                    startItem: data.length ? offset + 1 : 0,
                    endItem: data.length ? offset + data.length : 0,
                    hasNextPage: page < totalPages,
                    hasPreviousPage: page > 1 && totalRecords > 0
                }
            };

        } catch (e) {
            log.error({
                title: 'vw_rl_get_cost_centers - GET failed',
                details: e
            });

            return {
                success: false,
                error: {
                    type: e.name || 'UNEXPECTED_ERROR',
                    message: e.message || 'An unexpected error occurred while fetching cost centers',
                    details: e.toString()
                }
            };
        }
    }

    return {
        get: get
    };
});
