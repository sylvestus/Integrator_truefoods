/**
 * vw_rl_get_accounts.js
 *
 * NetSuite RESTlet that returns a paginated list of GL Accounts for the
 * Truefoods/Sanifu Laravel integrator. By default it returns the expense-type
 * accounts that vendor services (bills / expense lines) are charged to.
 *
 * Script ID:  customscript_vw_rl_get_accounts
 * Deploy ID:  customdeploy_vw_rl_get_accounts
 *
 * Method: GET
 * Endpoint (Laravel): POST /api/truefoods-sanifu/get/accounts
 *
 * Supported query parameters:
 *   page              // Page number (default 1)
 *   pageSize          // Records per page (default 50, max 1000)
 *   accountId         // Filter by account internal ID
 *   number            // Filter by account number (starts with)
 *   name              // Filter by account name / full name (contains, case-insensitive)
 *   accountType       // Comma-separated account type codes (default "Expense,OthExpense,COGS")
 *                     //   e.g. Expense, OthExpense, COGS, DeferExpense, OthCurrAsset, FixedAsset,
 *                     //        AcctPay, OthCurrLiab, Bank, Income, ...
 *   allTypes          // true = ignore accountType and return accounts of every type
 *   subsidiary        // Filter by subsidiary internal ID (accounts available to that subsidiary)
 *   parent            // Filter by parent account internal ID
 *   includeSummary    // true = also return summary (non-posting header) accounts (default false)
 *   isInactive        // true = only inactive, false/omitted = only active
 *   includeInactive   // true = return both active and inactive (overrides isInactive)
 *
 * Success response:
 * {
 *   "success": true,
 *   "data": [
 *     {
 *       "accountId":       620,
 *       "number":          "6200",
 *       "name":            "Professional Fees",
 *       "fullName":        "6000 Operating Expenses : 6200 Professional Fees",
 *       "accountType":     "Expense",
 *       "accountTypeName": "Expense",
 *       "parent":          600,
 *       "parentName":      "6000 Operating Expenses",
 *       "subsidiary":      "1, 2",
 *       "currency":        null,
 *       "currencyName":    null,
 *       "description":     null,
 *       "isSummary":       false,
 *       "isInactive":      false,
 *       "lastModifiedDate": "06/23/2026"
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
     * Account types used for vendor service / expense lines.
     */
    var DEFAULT_ACCOUNT_TYPES = ['Expense', 'OthExpense', 'COGS'];

    var SELECT_COLUMNS =
        'a.id AS accountid, ' +
        'a.acctnumber, ' +
        'a.accountsearchdisplaynamecopy AS accountname, ' +
        'a.fullname, ' +
        'a.accttype, ' +
        'BUILTIN.DF(a.accttype) AS accttypename, ' +
        'a.parent, ' +
        'BUILTIN.DF(a.parent) AS parentname, ' +
        'a.subsidiary, ' +
        'a.currency, ' +
        'BUILTIN.DF(a.currency) AS currencyname, ' +
        'a.description, ' +
        'a.issummary, ' +
        'a.isinactive, ' +
        'a.lastmodifieddate';

    var FROM_CLAUSE = 'FROM account a';

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

        if (isProvided(params.accountId)) {
            conditions.push('a.id = ?');
            values.push(parseInt(params.accountId, 10));
        }

        if (isProvided(params.number)) {
            conditions.push('a.acctnumber LIKE ?');
            values.push(String(params.number) + '%');
        }

        if (isProvided(params.name)) {
            conditions.push('(UPPER(a.accountsearchdisplaynamecopy) LIKE ? OR UPPER(a.fullname) LIKE ?)');
            var nameFilter = '%' + String(params.name).toUpperCase() + '%';
            values.push(nameFilter);
            values.push(nameFilter);
        }

        // Account type handling: defaults to expense-type accounts unless allTypes is set.
        if (!isTrue(params.allTypes)) {
            var types = isProvided(params.accountType)
                ? String(params.accountType).split(',').map(function (t) { return t.trim(); }).filter(Boolean)
                : DEFAULT_ACCOUNT_TYPES;

            if (types.length) {
                conditions.push('a.accttype IN (' + types.map(function () { return '?'; }).join(', ') + ')');
                types.forEach(function (t) { values.push(t); });
            }
        }

        if (isProvided(params.subsidiary)) {
            // Accounts can be shared across subsidiaries, so match on any assignment.
            conditions.push('EXISTS (SELECT 1 FROM accountsubsidiarymap m WHERE m.account = a.id AND m.subsidiary = ?)');
            values.push(parseInt(params.subsidiary, 10));
        }

        if (isProvided(params.parent)) {
            conditions.push('a.parent = ?');
            values.push(parseInt(params.parent, 10));
        }

        // Summary accounts cannot be posted to, so they are excluded by default.
        if (!isTrue(params.includeSummary)) {
            conditions.push('a.issummary = ?');
            values.push('F');
        }

        // Inactive handling: by default only active accounts are returned.
        if (!isTrue(params.includeInactive)) {
            conditions.push('a.isinactive = ?');
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
    function mapAccount(row) {
        return {
            accountId: toId(row.accountid),
            number: row.acctnumber || null,
            name: row.accountname || null,
            fullName: row.fullname || null,
            accountType: row.accttype || null,
            accountTypeName: row.accttypename || null,
            parent: toId(row.parent),
            parentName: row.parentname || null,
            subsidiary: isProvided(row.subsidiary) ? String(row.subsidiary) : null,
            currency: toId(row.currency),
            currencyName: row.currencyname || null,
            description: row.description || null,
            isSummary: toBool(row.issummary),
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
                        ' ORDER BY a.acctnumber, a.fullname, a.id' +
                        ' OFFSET ' + offset + ' ROWS FETCH NEXT ' + pageSize + ' ROWS ONLY',
                    params: where.values
                }).asMappedResults();
            }

            var data = rows.map(mapAccount);

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
                title: 'vw_rl_get_accounts - GET failed',
                details: e
            });

            return {
                success: false,
                error: {
                    type: e.name || 'UNEXPECTED_ERROR',
                    message: e.message || 'An unexpected error occurred while fetching accounts',
                    details: e.toString()
                }
            };
        }
    }

    return {
        get: get
    };
});
