/**
 * vw_rl_get_vendors.js
 *
 * NetSuite RESTlet that returns a paginated list of Vendors for the
 * Truefoods/Sanifu Laravel integrator.
 *
 * Script ID:  customscript_vw_rl_get_vendors
 * Deploy ID:  customdeploy_vw_rl_get_vendors
 *
 * Method: GET
 * Endpoint (Laravel): POST /api/truefoods-sanifu/get/vendors
 *
 * Supported query parameters:
 *   page              // Page number (default 1)
 *   pageSize          // Records per page (default 50, max 1000)
 *   vendorId          // Filter by vendor internal ID
 *   name              // Filter by vendor ID / company name / display name (contains, case-insensitive)
 *   email             // Filter by email (contains, case-insensitive)
 *   subsidiary        // Filter by subsidiary internal ID (any subsidiary the vendor is assigned to)
 *   category          // Filter by vendor category internal ID
 *   currency          // Filter by primary currency internal ID
 *   isInactive        // true = only inactive, false/omitted = only active
 *   includeInactive   // true = return both active and inactive (overrides isInactive)
 *   includeAddress    // false = omit the default billing address block (default true)
 *
 * Success response:
 * {
 *   "success": true,
 *   "data": [
 *     {
 *       "vendorId":        1234,
 *       "entityId":        "V0001 Acme Services Ltd",
 *       "companyName":     "Acme Services Ltd",
 *       "displayName":     "Acme Services Ltd",
 *       "legalName":       "Acme Services Limited",
 *       "isPerson":        false,
 *       "firstName":       null,
 *       "lastName":        null,
 *       "email":           "accounts@acme.co.ke",
 *       "phone":           "+254700000000",
 *       "subsidiary":      1,
 *       "subsidiaryName":  "Parent Company",
 *       "currency":        1,
 *       "currencyName":    "KES",
 *       "terms":           2,
 *       "termsName":       "Net 30",
 *       "category":        3,
 *       "categoryName":    "Services",
 *       "expenseAccount":      620,
 *       "expenseAccountName":  "6200 Professional Fees",
 *       "payablesAccount":     111,
 *       "payablesAccountName": "2000 Accounts Payable",
 *       "taxIdNum":        "P000000000A",
 *       "isInactive":      false,
 *       "dateCreated":     "01/15/2026",
 *       "lastModifiedDate": "06/23/2026",
 *       "address": {
 *         "addressee": "Acme Services Ltd",
 *         "attention": null,
 *         "address1":  "Mombasa Road",
 *         "address2":  null,
 *         "city":      "Nairobi",
 *         "state":     null,
 *         "zip":       "00100",
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
     * Base column list. The address columns come from the vendor's default
     * billing address (entityaddress joined on nkey = vendor.defaultbillingaddress).
     */
    var SELECT_COLUMNS =
        'v.id AS vendorid, ' +
        'v.entityid, ' +
        'v.companyname, ' +
        'v.altname, ' +
        'v.legalname, ' +
        'v.isperson, ' +
        'v.firstname, ' +
        'v.lastname, ' +
        'v.email, ' +
        'v.phone, ' +
        'v.subsidiary, ' +
        'BUILTIN.DF(v.subsidiary) AS subsidiaryname, ' +
        'v.currency, ' +
        'BUILTIN.DF(v.currency) AS currencyname, ' +
        'v.terms, ' +
        'BUILTIN.DF(v.terms) AS termsname, ' +
        'v.category, ' +
        'BUILTIN.DF(v.category) AS categoryname, ' +
        'v.expenseaccount, ' +
        'BUILTIN.DF(v.expenseaccount) AS expenseaccountname, ' +
        'v.payablesaccount, ' +
        'BUILTIN.DF(v.payablesaccount) AS payablesaccountname, ' +
        'v.taxidnum, ' +
        'v.isinactive, ' +
        'v.datecreated, ' +
        'v.lastmodifieddate, ' +
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
        'FROM vendor v ' +
        'LEFT JOIN entityaddress a ON a.nkey = v.defaultbillingaddress';

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

        if (isProvided(params.vendorId)) {
            conditions.push('v.id = ?');
            values.push(parseInt(params.vendorId, 10));
        }

        if (isProvided(params.name)) {
            conditions.push('(UPPER(v.entityid) LIKE ? OR UPPER(v.companyname) LIKE ? OR UPPER(v.altname) LIKE ?)');
            var nameFilter = '%' + String(params.name).toUpperCase() + '%';
            values.push(nameFilter);
            values.push(nameFilter);
            values.push(nameFilter);
        }

        if (isProvided(params.email)) {
            conditions.push('UPPER(v.email) LIKE ?');
            values.push('%' + String(params.email).toUpperCase() + '%');
        }

        if (isProvided(params.subsidiary)) {
            // A vendor can be shared across subsidiaries, so match on any assignment.
            conditions.push('EXISTS (SELECT 1 FROM vendorsubsidiaryrelationship r WHERE r.entity = v.id AND r.subsidiary = ?)');
            values.push(parseInt(params.subsidiary, 10));
        }

        if (isProvided(params.category)) {
            conditions.push('v.category = ?');
            values.push(parseInt(params.category, 10));
        }

        if (isProvided(params.currency)) {
            conditions.push('v.currency = ?');
            values.push(parseInt(params.currency, 10));
        }

        // Inactive handling: by default only active vendors are returned.
        if (!isTrue(params.includeInactive)) {
            conditions.push('v.isinactive = ?');
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
    function mapVendor(row, includeAddress) {
        var vendor = {
            vendorId: toId(row.vendorid),
            entityId: row.entityid || null,
            companyName: row.companyname || null,
            displayName: row.altname || null,
            legalName: row.legalname || null,
            isPerson: toBool(row.isperson),
            firstName: row.firstname || null,
            lastName: row.lastname || null,
            email: row.email || null,
            phone: row.phone || null,
            subsidiary: toId(row.subsidiary),
            subsidiaryName: row.subsidiaryname || null,
            currency: toId(row.currency),
            currencyName: row.currencyname || null,
            terms: toId(row.terms),
            termsName: row.termsname || null,
            category: toId(row.category),
            categoryName: row.categoryname || null,
            expenseAccount: toId(row.expenseaccount),
            expenseAccountName: row.expenseaccountname || null,
            payablesAccount: toId(row.payablesaccount),
            payablesAccountName: row.payablesaccountname || null,
            taxIdNum: row.taxidnum || null,
            isInactive: toBool(row.isinactive),
            dateCreated: row.datecreated || null,
            lastModifiedDate: row.lastmodifieddate || null
        };

        if (includeAddress) {
            vendor.address = {
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

        return vendor;
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
                        ' ORDER BY v.entityid, v.id' +
                        ' OFFSET ' + offset + ' ROWS FETCH NEXT ' + pageSize + ' ROWS ONLY',
                    params: where.values
                }).asMappedResults();
            }

            var data = rows.map(function (row) {
                return mapVendor(row, includeAddress);
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
                title: 'vw_rl_get_vendors - GET failed',
                details: e
            });

            return {
                success: false,
                error: {
                    type: e.name || 'UNEXPECTED_ERROR',
                    message: e.message || 'An unexpected error occurred while fetching vendors',
                    details: e.toString()
                }
            };
        }
    }

    return {
        get: get
    };
});
