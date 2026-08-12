/**
 * vw_rl_get_locations.js
 *
 * NetSuite RESTlet that returns a paginated list of Locations for the
 * Truefoods/Sanifu Laravel integrator.
 *
 * Script ID:  customscript_vw_rl_get_locations
 * Deploy ID:  customdeploy_vw_rl_get_locations
 *
 * Method: GET
 * Endpoint (Laravel): POST /api/truefoods-sanifu/get/locations
 *
 * Supported query parameters:
 *   page                    // Page number (default 1)
 *   pageSize                // Records per page (default 50, max 1000)
 *   locationId              // Filter by location internal ID
 *   name                    // Filter by name / full name (contains, case-insensitive)
 *   subsidiary              // Filter by subsidiary internal ID
 *   locationType            // Filter by location type internal ID
 *   parent                  // Filter by parent location internal ID
 *   makeInventoryAvailable  // true|false - filter by "Make Inventory Available"
 *   isInactive              // true = only inactive, false/omitted = only active
 *   includeInactive         // true = return both active and inactive (overrides isInactive)
 *   includeAddress          // false = omit the address block (default true)
 *
 * Success response:
 * {
 *   "success": true,
 *   "data": [
 *     {
 *       "locationId":  1,
 *       "name":        "Jogoo Road - FG Warehouse",
 *       "fullName":    "Jogoo Road - FG Warehouse",
 *       "subsidiary":  1,
 *       "subsidiaryName": "Parent Company",
 *       "parent":      null,
 *       "parentName":  null,
 *       "locationType":     2,
 *       "locationTypeName": "Warehouse",
 *       "isInactive":   false,
 *       "makeInventoryAvailable": true,
 *       "includeChildren": true,
 *       "tranPrefix":  null,
 *       "timeZone":    "Africa/Nairobi",
 *       "lastModifiedDate": "06/23/2026",
 *       "address": {
 *         "addressee": "Jogoo Road - FG Warehouse",
 *         "attention": null,
 *         "address1":  "Jogoo Road",
 *         "address2":  null,
 *         "city":      "Nairobi",
 *         "state":     null,
 *         "zip":       "41521-00100",
 *         "country":   "KE",
 *         "phone":     null
 *       }
 *     }
 *   ],
 *   "pagination": {
 *     "page": 1, "pageSize": 50, "totalRecords": 51, "totalPages": 2,
 *     "currentPageCount": 50, "startItem": 1, "endItem": 50,
 *     "hasNextPage": true, "hasPreviousPage": false
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

    /**
     * Base column list. The address columns come from the location's main
     * address record (locationmainaddress joined on nkey = location.mainaddress).
     */
    var SELECT_COLUMNS =
        'l.id AS locationid, ' +
        'l.name, ' +
        'l.fullname, ' +
        'l.subsidiary, ' +
        'BUILTIN.DF(l.subsidiary) AS subsidiaryname, ' +
        'l.parent, ' +
        'BUILTIN.DF(l.parent) AS parentname, ' +
        'l.locationtype, ' +
        'BUILTIN.DF(l.locationtype) AS locationtypename, ' +
        'l.isinactive, ' +
        'l.makeinventoryavailable, ' +
        'l.includechildren, ' +
        'l.tranprefix, ' +
        'l.timezone, ' +
        'l.lastmodifieddate, ' +
        'a.addressee, ' +
        'a.attention, ' +
        'a.addr1, ' +
        'a.addr2, ' +
        'a.city, ' +
        'a.state, ' +
        'a.zip, ' +
        'a.country, ' +
        'a.addrphone';

    var FROM_CLAUSE =
        'FROM location l ' +
        'LEFT JOIN locationmainaddress a ON a.nkey = l.mainaddress';

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

        if (isProvided(params.locationId)) {
            conditions.push('l.id = ?');
            values.push(parseInt(params.locationId, 10));
        }

        if (isProvided(params.name)) {
            conditions.push('(UPPER(l.name) LIKE ? OR UPPER(l.fullname) LIKE ?)');
            var nameFilter = '%' + String(params.name).toUpperCase() + '%';
            values.push(nameFilter);
            values.push(nameFilter);
        }

        if (isProvided(params.subsidiary)) {
            conditions.push('l.subsidiary = ?');
            values.push(parseInt(params.subsidiary, 10));
        }

        if (isProvided(params.locationType)) {
            conditions.push('l.locationtype = ?');
            values.push(parseInt(params.locationType, 10));
        }

        if (isProvided(params.parent)) {
            conditions.push('l.parent = ?');
            values.push(parseInt(params.parent, 10));
        }

        if (isProvided(params.makeInventoryAvailable)) {
            conditions.push('l.makeinventoryavailable = ?');
            values.push(isTrue(params.makeInventoryAvailable) ? 'T' : 'F');
        }

        // Inactive handling: by default only active locations are returned.
        if (!isTrue(params.includeInactive)) {
            conditions.push('l.isinactive = ?');
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
    function mapLocation(row, includeAddress) {
        var location = {
            locationId: toId(row.locationid),
            name: row.name || null,
            fullName: row.fullname || null,
            subsidiary: toId(row.subsidiary),
            subsidiaryName: row.subsidiaryname || null,
            parent: toId(row.parent),
            parentName: row.parentname || null,
            locationType: toId(row.locationtype),
            locationTypeName: row.locationtypename || null,
            isInactive: toBool(row.isinactive),
            makeInventoryAvailable: toBool(row.makeinventoryavailable),
            includeChildren: toBool(row.includechildren),
            tranPrefix: row.tranprefix || null,
            timeZone: row.timezone || null,
            lastModifiedDate: row.lastmodifieddate || null
        };

        if (includeAddress) {
            location.address = {
                addressee: row.addressee || null,
                attention: row.attention || null,
                address1: row.addr1 || null,
                address2: row.addr2 || null,
                city: row.city || null,
                state: row.state || null,
                zip: row.zip || null,
                country: row.country || null,
                phone: row.addrphone || null
            };
        }

        return location;
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

            var includeAddress = !isProvided(params.includeAddress) || isTrue(params.includeAddress);

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
                        ' ORDER BY l.name, l.id' +
                        ' OFFSET ' + offset + ' ROWS FETCH NEXT ' + pageSize + ' ROWS ONLY',
                    params: where.values
                }).asMappedResults();
            }

            var data = rows.map(function (row) {
                return mapLocation(row, includeAddress);
            });

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
                title: 'vw_rl_get_locations - GET failed',
                details: e
            });

            return {
                success: false,
                error: {
                    type: e.name || 'UNEXPECTED_ERROR',
                    message: e.message || 'An unexpected error occurred while fetching locations',
                    details: e.toString()
                }
            };
        }
    }

    return {
        get: get
    };
});
