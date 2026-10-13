/**
 * vw_rl_get_raw_materials.js
 *
 * NetSuite RESTlet that returns a paginated list of Raw Materials for the
 * Truefoods/Sanifu Laravel integrator. In Truefoods raw materials are the
 * Inventory Items (item type InvtPart); assembly items are finished goods and
 * are excluded.
 *
 * Script ID:  customscript_vw_rl_get_raw_materials
 * Deploy ID:  customdeploy_vw_rl_get_raw_materials
 *
 * Method: GET
 * Endpoint (Laravel): POST /api/truefoods-sanifu/get/raw-materials
 *
 * Supported query parameters:
 *   page              // Page number (default 1)
 *   pageSize          // Records per page (default 50, max 1000)
 *   itemId            // Filter by item internal ID
 *   name              // Filter by item code / display name (contains, case-insensitive)
 *   subsidiary        // Filter by subsidiary internal ID (items available to that subsidiary)
 *   costCenter        // Filter by cost center (class) internal ID
 *   division          // Filter by division (department) internal ID
 *   location          // Limit the locations block (and inStockOnly) to this location internal ID
 *   inStockOnly       // true = only items with quantity on hand > 0 (at location, if given)
 *   isInactive        // true = only inactive, false/omitted = only active
 *   includeInactive   // true = return both active and inactive (overrides isInactive)
 *   includeLocations  // true = include per-location stock quantities (default false)
 *
 * Success response:
 * {
 *   "success": true,
 *   "data": [
 *     {
 *       "itemId":          512,
 *       "itemCode":        "RM-0001",
 *       "displayName":     "Wheat Flour 50kg",
 *       "description":     "Wheat flour, 50kg bag",
 *       "itemType":        "InvtPart",
 *       "upcCode":         null,
 *       "unitsType":       3,
 *       "unitsTypeName":   "Weight",
 *       "stockUnit":       5,
 *       "stockUnitName":   "Kg",
 *       "purchaseUnit":    6,
 *       "purchaseUnitName": "Bag (50kg)",
 *       "consumptionUnit": 5,
 *       "consumptionUnitName": "Kg",
 *       "purchasePrice":   4200,
 *       "averageCost":     84.5,
 *       "lastPurchasePrice": 4250,
 *       "costingMethod":   "AVG",
 *       "costCenter":      4,
 *       "costCenterName":  "Production",
 *       "division":        2,
 *       "divisionName":    "Manufacturing",
 *       "subsidiary":      "1",
 *       "assetAccount":    215,
 *       "assetAccountName": "1310 Inventory - Raw Materials",
 *       "isInactive":      false,
 *       "dateCreated":     "01/15/2026",
 *       "lastModifiedDate": "06/23/2026",
 *       "locations": [
 *         {
 *           "locationId":        1,
 *           "locationName":      "Jogoo Road - RM Store",
 *           "quantityOnHand":    1200,
 *           "quantityAvailable": 1100,
 *           "quantityOnOrder":   500,
 *           "quantityCommitted": 100,
 *           "quantityBackOrdered": 0,
 *           "averageCost":       84.5,
 *           "lastPurchasePrice": 4250,
 *           "reorderPoint":      300,
 *           "preferredStockLevel": 1500
 *         }
 *       ]
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
     * Raw materials are inventory items only (assemblies are finished goods).
     */
    var RAW_MATERIAL_ITEM_TYPE = 'InvtPart';

    var SELECT_COLUMNS =
        'i.id AS itemid, ' +
        'i.itemid AS itemcode, ' +
        'i.displayname, ' +
        'i.purchasedescription, ' +
        'i.description, ' +
        'i.itemtype, ' +
        'i.upccode, ' +
        'i.unitstype, ' +
        'BUILTIN.DF(i.unitstype) AS unitstypename, ' +
        'i.stockunit, ' +
        'BUILTIN.DF(i.stockunit) AS stockunitname, ' +
        'i.purchaseunit, ' +
        'BUILTIN.DF(i.purchaseunit) AS purchaseunitname, ' +
        'i.consumptionunit, ' +
        'BUILTIN.DF(i.consumptionunit) AS consumptionunitname, ' +
        'i.cost, ' +
        'i.averagecost, ' +
        'i.lastpurchaseprice, ' +
        'i.costingmethod, ' +
        'i.class, ' +
        'BUILTIN.DF(i.class) AS classname, ' +
        'i.department, ' +
        'BUILTIN.DF(i.department) AS departmentname, ' +
        'i.subsidiary, ' +
        'i.assetaccount, ' +
        'BUILTIN.DF(i.assetaccount) AS assetaccountname, ' +
        'i.isinactive, ' +
        'i.createddate, ' +
        'i.lastmodifieddate';

    var FROM_CLAUSE = 'FROM item i';

    var LOCATION_COLUMNS =
        'il.item, ' +
        'il.location, ' +
        'BUILTIN.DF(il.location) AS locationname, ' +
        'il.quantityonhand, ' +
        'il.quantityavailable, ' +
        'il.quantityonorder, ' +
        'il.quantitycommitted, ' +
        'il.quantitybackordered, ' +
        'il.averagecostmli, ' +
        'il.lastpurchasepricemli, ' +
        'il.reorderpoint, ' +
        'il.preferredstocklevel';

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
     * Converts a numeric column to a number (or null when empty).
     */
    function toNumber(value) {
        if (!isProvided(value)) {
            return null;
        }
        var parsed = parseFloat(value);
        return isNaN(parsed) ? null : parsed;
    }

    /**
     * Builds the WHERE clause and bind parameters from the request filters.
     *
     * @param {Object} params - raw RESTlet query parameters
     * @returns {{clause: string, values: Array}}
     */
    function buildWhere(params) {
        var conditions = ['i.itemtype = ?'];
        var values = [RAW_MATERIAL_ITEM_TYPE];

        if (isProvided(params.itemId)) {
            conditions.push('i.id = ?');
            values.push(parseInt(params.itemId, 10));
        }

        if (isProvided(params.name)) {
            conditions.push('(UPPER(i.itemid) LIKE ? OR UPPER(i.displayname) LIKE ?)');
            var nameFilter = '%' + String(params.name).toUpperCase() + '%';
            values.push(nameFilter);
            values.push(nameFilter);
        }

        if (isProvided(params.subsidiary)) {
            // Items can be shared across subsidiaries, so match on any assignment.
            conditions.push('EXISTS (SELECT 1 FROM itemsubsidiarymap m WHERE m.item = i.id AND m.subsidiary = ?)');
            values.push(parseInt(params.subsidiary, 10));
        }

        if (isProvided(params.costCenter)) {
            conditions.push('i.class = ?');
            values.push(parseInt(params.costCenter, 10));
        }

        if (isProvided(params.division)) {
            conditions.push('i.department = ?');
            values.push(parseInt(params.division, 10));
        }

        if (isTrue(params.inStockOnly)) {
            if (isProvided(params.location)) {
                conditions.push('EXISTS (SELECT 1 FROM inventoryitemlocations il WHERE il.item = i.id AND il.location = ? AND il.quantityonhand > 0)');
                values.push(parseInt(params.location, 10));
            } else {
                conditions.push('EXISTS (SELECT 1 FROM inventoryitemlocations il WHERE il.item = i.id AND il.quantityonhand > 0)');
            }
        }

        // Inactive handling: by default only active items are returned.
        if (!isTrue(params.includeInactive)) {
            conditions.push('i.isinactive = ?');
            values.push(isTrue(params.isInactive) ? 'T' : 'F');
        }

        return {
            clause: ' WHERE ' + conditions.join(' AND '),
            values: values
        };
    }

    /**
     * Loads per-location stock for the given item IDs, grouped by item ID.
     * Without a location filter only locations holding or expecting stock are returned.
     *
     * @param {Array<number>} itemIds - validated integer item IDs from the current page
     * @param {Object} params - raw RESTlet query parameters
     * @returns {Object<number, Array>}
     */
    function loadLocations(itemIds, params) {
        var byItem = {};
        if (!itemIds.length) {
            return byItem;
        }

        // Item IDs come from our own query results (integers), so they are inlined.
        var sql = 'SELECT ' + LOCATION_COLUMNS + ' FROM inventoryitemlocations il' +
            ' WHERE il.item IN (' + itemIds.join(', ') + ')';
        var values = [];

        if (isProvided(params.location)) {
            sql += ' AND il.location = ?';
            values.push(parseInt(params.location, 10));
        } else {
            sql += ' AND (NVL(il.quantityonhand, 0) <> 0 OR NVL(il.quantityonorder, 0) <> 0)';
        }

        sql += ' ORDER BY il.item, BUILTIN.DF(il.location)';

        query.runSuiteQL({ query: sql, params: values }).asMappedResults().forEach(function (row) {
            var itemId = toId(row.item);
            if (!byItem[itemId]) {
                byItem[itemId] = [];
            }
            byItem[itemId].push({
                locationId: toId(row.location),
                locationName: row.locationname || null,
                quantityOnHand: toNumber(row.quantityonhand) || 0,
                quantityAvailable: toNumber(row.quantityavailable) || 0,
                quantityOnOrder: toNumber(row.quantityonorder) || 0,
                quantityCommitted: toNumber(row.quantitycommitted) || 0,
                quantityBackOrdered: toNumber(row.quantitybackordered) || 0,
                averageCost: toNumber(row.averagecostmli),
                lastPurchasePrice: toNumber(row.lastpurchasepricemli),
                reorderPoint: toNumber(row.reorderpoint),
                preferredStockLevel: toNumber(row.preferredstocklevel)
            });
        });

        return byItem;
    }

    /**
     * Maps a SuiteQL row to the API response shape.
     */
    function mapRawMaterial(row) {
        return {
            itemId: toId(row.itemid),
            itemCode: row.itemcode || null,
            displayName: row.displayname || null,
            description: row.purchasedescription || row.description || null,
            itemType: row.itemtype || null,
            upcCode: row.upccode || null,
            unitsType: toId(row.unitstype),
            unitsTypeName: row.unitstypename || null,
            stockUnit: toId(row.stockunit),
            stockUnitName: row.stockunitname || null,
            purchaseUnit: toId(row.purchaseunit),
            purchaseUnitName: row.purchaseunitname || null,
            consumptionUnit: toId(row.consumptionunit),
            consumptionUnitName: row.consumptionunitname || null,
            purchasePrice: toNumber(row.cost),
            averageCost: toNumber(row.averagecost),
            lastPurchasePrice: toNumber(row.lastpurchaseprice),
            costingMethod: row.costingmethod || null,
            costCenter: toId(row.class),
            costCenterName: row.classname || null,
            division: toId(row.department),
            divisionName: row.departmentname || null,
            subsidiary: isProvided(row.subsidiary) ? String(row.subsidiary) : null,
            assetAccount: toId(row.assetaccount),
            assetAccountName: row.assetaccountname || null,
            isInactive: toBool(row.isinactive),
            dateCreated: row.createddate || null,
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

            var includeLocations = isTrue(params.includeLocations);

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
                        ' ORDER BY i.itemid, i.id' +
                        ' OFFSET ' + offset + ' ROWS FETCH NEXT ' + pageSize + ' ROWS ONLY',
                    params: where.values
                }).asMappedResults();
            }

            var data = rows.map(mapRawMaterial);

            if (includeLocations) {
                var locationsByItem = loadLocations(data.map(function (item) { return item.itemId; }), params);
                data.forEach(function (item) {
                    item.locations = locationsByItem[item.itemId] || [];
                });
            }

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
                title: 'vw_rl_get_raw_materials - GET failed',
                details: e
            });

            return {
                success: false,
                error: {
                    type: e.name || 'UNEXPECTED_ERROR',
                    message: e.message || 'An unexpected error occurred while fetching raw materials',
                    details: e.toString()
                }
            };
        }
    }

    return {
        get: get
    };
});
