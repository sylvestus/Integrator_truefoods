/**
 * vw_rl_create_estimate.js
 *
 * NetSuite RESTlet that creates an Estimate (record type "estimate" / quote)
 * from a JSON payload sent by the Truefoods/Sanifu Laravel integrator.
 *
 * Script ID:  customscript_vw_rl_create_estimate
 * Deploy ID:  customdeploy_vw_rl_create_estimate
 *
 * Method: POST
 * Endpoint (Laravel): POST /api/truefoods-sanifu/create/estimate
 *
 * Expected request body (JSON):
 * {
 *   "customerId":        1378,                  // (mandatory) Customer internal ID
 *   "entityStatus":      8,                     // Estimate status internal ID
 *   "memo":              "Quote for Q2",
 *   "tranDate":          "2026-05-21",          // YYYY-MM-DD
 *   "expectedCloseDate": "2026-06-15",          // YYYY-MM-DD
 *   "dueDate":           "2026-06-30",
 *   "shipDate":          "2026-06-20",
 *   "otherRefNum":       "PO-EST-12345",
 *   "terms":             3,
 *   "salesRep":          1307,
 *   "department":        2,
 *   "classId":           1,
 *   "location":          5,
 *   "subsidiary":        1,
 *   "currency":          1,
 *   "shipTo":            1234,                  // Customer address-book line ID
 *   "salesType":         1,                     // Custom field (TruFoods)
 *   "channel":           2,                     // Custom field (TruFoods)
 *   "region":            3,                     // Custom field (TruFoods)
 *   "widgetLink":        "https://...",         // Custom field (TruFoods)
 *   "probability":       75,
 *   "items": [
 *     {
 *       "itemId":      355,
 *       "quantity":    2,
 *       "rate":        500,
 *       "amount":      1000,
 *       "description": "Burger",
 *       "taxCode":     7,
 *       "location":    5
 *     }
 *   ]
 * }
 *
 * Success response:
 * {
 *   "success":    true,
 *   "estimateId": 3890011,
 *   "tranId":     "EST123",
 *   "message":    "Estimate created successfully"
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
define(['N/record', 'N/log', 'N/error'], function (record, log, error) {

    /**
     * Custom field mappings (TruFoods specific).
     * Adjust the right-hand side to the actual custbody_* ids in your account.
     */
    var CUSTOM_FIELDS = {
        salesType:  'custbody_tf_sales_type',
        channel:    'custbody_tf_channel',
        region:     'custbody_tf_region',
        widgetLink: 'custbody_tf_widget_link'
    };

    /**
     * Required top-level fields.
     */
    var REQUIRED_FIELDS = ['customerId', 'items'];

    /**
     * Required per-line fields.
     */
    var REQUIRED_LINE_FIELDS = ['itemId', 'quantity'];

    /**
     * Entry point for POST requests.
     *
     * @param {Object} requestBody - Parsed JSON payload
     * @returns {Object}
     */
    function doPost(requestBody) {
        try {
            log.debug({ title: 'create_estimate: request', details: JSON.stringify(requestBody) });

            // ----- Validate payload --------------------------------------------------
            for (var i = 0; i < REQUIRED_FIELDS.length; i++) {
                var key = REQUIRED_FIELDS[i];
                if (requestBody[key] === undefined || requestBody[key] === null || requestBody[key] === '') {
                    return buildError('MISSING_REQUIRED_PARAMETER', key + ' is required');
                }
            }

            if (!Array.isArray(requestBody.items) || requestBody.items.length === 0) {
                return buildError('INVALID_ITEMS', 'items must be a non-empty array');
            }

            for (var li = 0; li < requestBody.items.length; li++) {
                var line = requestBody.items[li];
                for (var lf = 0; lf < REQUIRED_LINE_FIELDS.length; lf++) {
                    var lkey = REQUIRED_LINE_FIELDS[lf];
                    if (line[lkey] === undefined || line[lkey] === null || line[lkey] === '') {
                        return buildError(
                            'MISSING_REQUIRED_LINE_PARAMETER',
                            'items[' + li + '].' + lkey + ' is required'
                        );
                    }
                }
            }

            // ----- Build the Estimate record ----------------------------------------
            var estimate = record.create({
                type: record.Type.ESTIMATE,
                isDynamic: true
            });

            // -- Header --
            estimate.setValue({ fieldId: 'entity', value: requestBody.customerId });

            setIfPresent(estimate, 'entitystatus',      requestBody.entityStatus);
            setIfPresent(estimate, 'memo',              requestBody.memo);
            setIfPresent(estimate, 'tranid',            requestBody.tranId);
            setIfPresent(estimate, 'otherrefnum',       requestBody.otherRefNum);
            setIfPresent(estimate, 'terms',             requestBody.terms);
            setIfPresent(estimate, 'salesrep',          requestBody.salesRep);
            setIfPresent(estimate, 'department',        requestBody.department);
            setIfPresent(estimate, 'class',             requestBody.classId);
            setIfPresent(estimate, 'location',          requestBody.location);
            setIfPresent(estimate, 'subsidiary',        requestBody.subsidiary);
            setIfPresent(estimate, 'currency',          requestBody.currency);
            setIfPresent(estimate, 'shipmethod',        requestBody.shipMethod);
            setIfPresent(estimate, 'probability',       requestBody.probability);

            // Dates
            setDateIfPresent(estimate, 'trandate',           requestBody.tranDate);
            setDateIfPresent(estimate, 'expectedclosedate',  requestBody.expectedCloseDate);
            setDateIfPresent(estimate, 'duedate',            requestBody.dueDate);
            setDateIfPresent(estimate, 'shipdate',           requestBody.shipDate);

            // Ship-to address (customer address-book line)
            setIfPresent(estimate, 'shipaddresslist', requestBody.shipTo);

            // Inline ship address overrides (only if provided)
            applyInlineShipAddress(estimate, requestBody);

            // Custom fields (TruFoods specific)
            Object.keys(CUSTOM_FIELDS).forEach(function (k) {
                if (requestBody[k] !== undefined && requestBody[k] !== null && requestBody[k] !== '') {
                    try {
                        estimate.setValue({ fieldId: CUSTOM_FIELDS[k], value: requestBody[k] });
                    } catch (e) {
                        log.error({
                            title: 'create_estimate: custom field set failed',
                            details: 'field=' + CUSTOM_FIELDS[k] + ' err=' + e
                        });
                    }
                }
            });

            // -- Line items --
            requestBody.items.forEach(function (line, idx) {
                estimate.selectNewLine({ sublistId: 'item' });

                estimate.setCurrentSublistValue({
                    sublistId: 'item',
                    fieldId:   'item',
                    value:     line.itemId
                });
                estimate.setCurrentSublistValue({
                    sublistId: 'item',
                    fieldId:   'quantity',
                    value:     line.quantity
                });

                setSublistIfPresent(estimate, 'item', 'rate',        line.rate);
                setSublistIfPresent(estimate, 'item', 'amount',      line.amount);
                setSublistIfPresent(estimate, 'item', 'description', line.description);
                setSublistIfPresent(estimate, 'item', 'taxcode',     line.taxCode);
                setSublistIfPresent(estimate, 'item', 'location',    line.location);

                estimate.commitLine({ sublistId: 'item' });
            });

            // ----- Save -------------------------------------------------------------
            var estimateId = estimate.save({
                enableSourcing:        true,
                ignoreMandatoryFields: false
            });

            // Re-load lightly to return the tranId
            var saved = record.load({ type: record.Type.ESTIMATE, id: estimateId, isDynamic: false });
            var tranId = saved.getValue({ fieldId: 'tranid' });

            log.audit({ title: 'create_estimate: success', details: 'id=' + estimateId + ' tranId=' + tranId });

            return {
                success:    true,
                estimateId: estimateId,
                tranId:     tranId,
                message:    'Estimate created successfully'
            };

        } catch (ex) {
            log.error({
                title:   'create_estimate: exception',
                details: (ex && ex.toString ? ex.toString() : ex) + ' | stack: ' + (ex && ex.stack)
            });

            return {
                success: false,
                error: {
                    type:    (ex && ex.name)    ? ex.name    : 'UNEXPECTED_ERROR',
                    message: (ex && ex.message) ? ex.message : 'An unexpected error occurred',
                    details: (ex && ex.toString) ? ex.toString() : null
                }
            };
        }
    }

    // ------------------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------------------

    function setIfPresent(rec, fieldId, value) {
        if (value === undefined || value === null || value === '') return;
        rec.setValue({ fieldId: fieldId, value: value });
    }

    function setDateIfPresent(rec, fieldId, value) {
        if (value === undefined || value === null || value === '') return;
        var d = new Date(value);
        if (isNaN(d.getTime())) {
            // Try DD/MM/YYYY
            var parts = String(value).split('/');
            if (parts.length === 3) {
                d = new Date(parts[2] + '-' + parts[1] + '-' + parts[0]);
            }
        }
        if (!isNaN(d.getTime())) {
            rec.setValue({ fieldId: fieldId, value: d });
        }
    }

    function setSublistIfPresent(rec, sublistId, fieldId, value) {
        if (value === undefined || value === null || value === '') return;
        rec.setCurrentSublistValue({ sublistId: sublistId, fieldId: fieldId, value: value });
    }

    function applyInlineShipAddress(rec, body) {
        var hasAny = ['shipAddressee','shipAttention','shipAddr1','shipAddr2','shipCity',
                      'shipState','shipZip','shipCountry','shipPhone']
                     .some(function (k) { return body[k] !== undefined && body[k] !== null && body[k] !== ''; });

        if (!hasAny) return;

        var subrec = rec.getSubrecord({ fieldId: 'shippingaddress' });
        if (!subrec) return;

        if (body.shipAddressee) subrec.setValue({ fieldId: 'addressee',  value: body.shipAddressee });
        if (body.shipAttention) subrec.setValue({ fieldId: 'attention',  value: body.shipAttention });
        if (body.shipAddr1)     subrec.setValue({ fieldId: 'addr1',      value: body.shipAddr1 });
        if (body.shipAddr2)     subrec.setValue({ fieldId: 'addr2',      value: body.shipAddr2 });
        if (body.shipCity)      subrec.setValue({ fieldId: 'city',       value: body.shipCity });
        if (body.shipState)     subrec.setValue({ fieldId: 'state',      value: body.shipState });
        if (body.shipZip)       subrec.setValue({ fieldId: 'zip',        value: body.shipZip });
        if (body.shipCountry)   subrec.setValue({ fieldId: 'country',    value: body.shipCountry });
        if (body.shipPhone)     subrec.setValue({ fieldId: 'addrphone',  value: body.shipPhone });
    }

    function buildError(type, message) {
        return {
            success: false,
            error: { type: type, message: message }
        };
    }

    return {
        post: doPost
    };
});
