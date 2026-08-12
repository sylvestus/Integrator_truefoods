/**
 * @NApiVersion 2.1
 * @NScriptType Restlet
 * @NModuleScope SameAccount
 */
define(['N/record', 'N/error'], (record, error) => {

    /**
     * POST handler to create a sales order
     * @param {Object} requestBody - Request body containing sales order data
     * @returns {Object} Response with creation status
     */
    const post = (requestBody) => {
        try {
            log.audit('POST Request', 'Received data: ' + JSON.stringify(requestBody));

            // Validate required parameters
            if (!requestBody.customerId) {
                throw error.create({
                    name: 'MISSING_REQUIRED_PARAMETER',
                    message: 'customerId is required'
                });
            }

            if (!requestBody.items || !Array.isArray(requestBody.items) || requestBody.items.length === 0) {
                throw error.create({
                    name: 'MISSING_REQUIRED_PARAMETER',
                    message: 'items array is required and must contain at least one item'
                });
            }

            // Create the sales order
            const salesOrderRec = record.create({
                type: record.Type.SALES_ORDER,
                isDynamic: true
            });

            // Set customer
            salesOrderRec.setValue({
                fieldId: 'entity',
                value: requestBody.customerId,
                ignoreFieldChange: true
            });

            //Set external id
            salesOrderRec.setValue({
                fieldId: 'externalid',
                value: requestBody.otherRefNum,
                ignoreFieldChange: true
            });

            // Set header fields if provided
            setHeaderFields(salesOrderRec, requestBody);

            // Add line items
            const lineItemResults = addLineItems(salesOrderRec, requestBody.items);

            // Set shipping address last to prevent sourcing from resetting it
            if (requestBody.shipTo) {
                const lineId = resolveShipAddressLine(requestBody.customerId, requestBody.shipTo);
                if (!lineId) {
                    throw error.create({
                        name: 'INVALID_SHIPPING_ADDRESS',
                        message: 'shipTo ' + requestBody.shipTo + ' is not in customer ' + requestBody.customerId + "'s address book"
                    });
                }
                salesOrderRec.setValue({
                    fieldId: 'shipaddresslist',
                    value: parseInt(lineId, 10),
                    ignoreFieldChange: true
                });
                log.audit('shipaddresslist set', 'lineId: ' + lineId + ' (as int: ' + parseInt(lineId, 10) + ')');
            }

            // Save the record
            const recordId = salesOrderRec.save({
                enableSourcing: false,
                ignoreMandatoryFields: false
            });

            // After save, update shipaddresslist if shipTo was provided
            if (requestBody.shipTo) {
                const lineId = resolveShipAddressLine(requestBody.customerId, requestBody.shipTo);
                if (lineId) {
                    const soRec = record.load({
                        type: record.Type.SALES_ORDER,
                        id: recordId,
                        isDynamic: true
                    });
                    soRec.setValue({
                        fieldId: 'shipaddresslist',
                        value: parseInt(lineId, 10)
                    });
                    soRec.save({
                        enableSourcing: false,
                        ignoreMandatoryFields: false
                    });
                    log.audit('shipaddresslist updated post-save', 'SO: ' + recordId + ', lineId: ' + lineId);
                }
            }

            return {
                success: true,
                message: 'Sales order created successfully',
                orderId: recordId,
                lineItems: lineItemResults
            };

        } catch (e) {
            log.error({
                title: 'Error in POST handler',
                details: 'Message: ' + e.message + ' | Stack: ' + e.stack
            });

            return {
                success: false,
                error: {
                    message: e.message,
                    type: e.name,
                    details: e.stack
                }
            };
        }
    };

    /**
     * Parse a date string into a Date at LOCAL (server) midnight.
     *
     * IMPORTANT: do not use `new Date('YYYY-MM-DD')` here. JavaScript parses a
     * date-only ISO string as UTC midnight; when NetSuite renders it in the
     * account/server timezone (any negative UTC offset) the date shifts back one
     * day (e.g. 2026-06-18 saved as the 17th). Building the Date from explicit
     * y/m/d numbers anchors it to local midnight, avoiding the shift.
     *
     * Supports "YYYY-MM-DD" and "DD/MM/YYYY".
     *
     * @param {string} value
     * @returns {Date|null}
     */
    const parseLocalDate = (value) => {
        const s = String(value).trim();
        let y, m, day, parts;

        // YYYY-MM-DD (optionally with a trailing time component)
        if (/^\d{4}-\d{2}-\d{2}/.test(s)) {
            parts = s.substring(0, 10).split('-');
            y = parseInt(parts[0], 10);
            m = parseInt(parts[1], 10) - 1;
            day = parseInt(parts[2], 10);
            return new Date(y, m, day);
        }

        // DD/MM/YYYY
        if (s.indexOf('/') !== -1) {
            parts = s.split('/');
            if (parts.length === 3) {
                day = parseInt(parts[0], 10);
                m = parseInt(parts[1], 10) - 1;
                y = parseInt(parts[2], 10);
                return new Date(y, m, day);
            }
        }

        // Fallback: let the engine try (e.g. full ISO datetimes)
        const d = new Date(s);
        return isNaN(d.getTime()) ? null : d;
    };

    /**
     * Set header fields on the sales order
     * @param {record.Record} salesOrderRec - Sales order record
     * @param {Object} data - Data containing fields to set
     */
    const setHeaderFields = (salesOrderRec, data) => {
        const setFieldSafely = (fieldId, value) => {
            try {
                if (value !== undefined && value !== null && value !== '') {
                    salesOrderRec.setValue({ fieldId: fieldId, value: value });
                    log.debug('Field set', fieldId + ' = ' + value);
                }
            } catch (e) {
                log.error('Error setting field', fieldId + ': ' + e.message);
            }
        };

        // Transaction fields
        setFieldSafely('memo', data.memo);
        setFieldSafely('tranid', data.tranId); // Document number
        setFieldSafely('otherrefnum', data.otherRefNum); // PO Number
        setFieldSafely('custbody_po_number', data.poNumber); // Alternative PO field

        // Dates
        if (data.tranDate) {
            try {
                salesOrderRec.setValue({ fieldId: 'trandate', value: parseLocalDate(data.tranDate) });
            } catch (e) {
                log.error('Error setting trandate', e.message);
            }
        }

        if (data.shipDate) {
            try {
                salesOrderRec.setValue({ fieldId: 'shipdate', value: parseLocalDate(data.shipDate) });
            } catch (e) {
                log.error('Error setting shipdate', e.message);
            }
        }

        if (data.dueDate) {
            try {
                salesOrderRec.setValue({ fieldId: 'duedate', value: parseLocalDate(data.dueDate) });
            } catch (e) {
                log.error('Error setting duedate', e.message);
            }
        }

        // Standard fields
        setFieldSafely('subsidiary', data.subsidiary);
        setFieldSafely('terms', data.terms);
        setFieldSafely('salesrep', data.salesRep);
        setFieldSafely('department', data.department);
        setFieldSafely('class', data.classId);
        setFieldSafely('location', data.location);
        setFieldSafely('shipmethod', data.shipMethod);
        setFieldSafely('currency', data.currency);

        // Ship-to is set after line items, right before save, to prevent sourcing from resetting it.

        // Custom fields (TruFoods specific)
        setFieldSafely('custbody_tf_sales_type', data.salesType);
        setFieldSafely('custbody_tf_channel', data.channel);
        setFieldSafely('custbody_tf_region', data.region);
        setFieldSafely('custbody_widget_link', data.widgetLink);

        log.audit('Header fields set', 'Customer ID: ' + data.customerId);
    };

    /**
     * Add line items to the sales order
     * @param {record.Record} salesOrderRec - Sales order record
     * @param {Array} items - Array of items to add
     * @returns {Object} Results with success/failure counts and errors
     */
    const addLineItems = (salesOrderRec, items) => {
        const results = {
            attempted: 0,
            succeeded: 0,
            failed: 0,
            errors: []
        };

        items.forEach((item, index) => {
            results.attempted++;

            try {
                // Validate required item fields
                if (!item.itemId) {
                    const errorMsg = 'Missing itemId';
                    log.error(errorMsg, 'Item at index ' + index + ': ' + JSON.stringify(item));
                    results.failed++;
                    results.errors.push({
                        itemIndex: index,
                        error: errorMsg,
                        item: item
                    });
                    return;
                }

                // Select new line
                salesOrderRec.selectNewLine({
                    sublistId: 'item'
                });

                // Set item
                salesOrderRec.setCurrentSublistValue({
                    sublistId: 'item',
                    fieldId: 'item',
                    value: item.itemId
                });

                // Set quantity (default to 1 if not provided)
                salesOrderRec.setCurrentSublistValue({
                    sublistId: 'item',
                    fieldId: 'quantity',
                    value: item.quantity || 1
                });

                // Set rate if provided
                if (item.rate !== undefined && item.rate !== null) {
                    salesOrderRec.setCurrentSublistValue({
                        sublistId: 'item',
                        fieldId: 'rate',
                        value: item.rate
                    });
                }

                // Set amount if provided (will override rate calculation)
                if (item.amount !== undefined && item.amount !== null) {
                    salesOrderRec.setCurrentSublistValue({
                        sublistId: 'item',
                        fieldId: 'amount',
                        value: item.amount
                    });
                }

                // Set description if provided
                if (item.description !== undefined && item.description !== null) {
                    salesOrderRec.setCurrentSublistValue({
                        sublistId: 'item',
                        fieldId: 'description',
                        value: item.description
                    });
                }

                // Set tax code if provided
                if (item.taxCode !== undefined && item.taxCode !== null) {
                    salesOrderRec.setCurrentSublistValue({
                        sublistId: 'item',
                        fieldId: 'taxcode',
                        value: item.taxCode
                    });
                }

                // Set location if provided
                if (item.location !== undefined && item.location !== null) {
                    salesOrderRec.setCurrentSublistValue({
                        sublistId: 'item',
                        fieldId: 'location',
                        value: item.location
                    });
                }

                // Set price level if provided
                if (item.priceLevel !== undefined && item.priceLevel !== null) {
                    salesOrderRec.setCurrentSublistValue({
                        sublistId: 'item',
                        fieldId: 'price',
                        value: item.priceLevel
                    });
                }

                // Commit the line
                salesOrderRec.commitLine({
                    sublistId: 'item'
                });

                results.succeeded++;
                log.audit('Line item added', 'Item Index: ' + index + ', Item ID: ' + item.itemId);

            } catch (e) {
                results.failed++;
                const errorInfo = {
                    itemIndex: index,
                    error: e.message,
                    errorType: e.name,
                    item: item,
                    stack: e.stack
                };
                results.errors.push(errorInfo);
                log.error('Error adding line item', JSON.stringify(errorInfo));
            }
        });

        return results;
    };

    /**
     * Resolve a shipTo value against the customer's address book.
     * Accepts either the addressbook line's internal ID or the address
     * subrecord's internal ID. Returns the addressbook line ID (what
     * shipaddresslist expects), or null if no match.
     */
    const resolveShipAddressLine = (customerId, shipTo) => {
        const target = String(shipTo);
        const customerRec = record.load({ type: record.Type.CUSTOMER, id: customerId });
        const lineCount = customerRec.getLineCount({ sublistId: 'addressbook' });
        const debug = [];

        const tryField = (fieldId, line) => {
            try {
                return customerRec.getSublistValue({ sublistId: 'addressbook', fieldId: fieldId, line: line });
            } catch (e) { return null; }
        };

        for (let i = 0; i < lineCount; i++) {
            const lineId = tryField('id', i) || tryField('internalid', i);
            const addrId = tryField('addrid', i) || tryField('addressid', i);
            let subrecId = null;
            try {
                const sub = customerRec.getSublistSubrecord({ sublistId: 'addressbook', fieldId: 'addressbookaddress', line: i });
                if (sub && sub.id) subrecId = sub.id;
            } catch (e) { /* subrecord not accessible */ }

            debug.push({ line: i, lineId: lineId, addrId: addrId, subrecId: subrecId });

            if (
                (lineId && String(lineId) === target) ||
                (addrId && String(addrId) === target) ||
                (subrecId && String(subrecId) === target)
            ) {
                return lineId;
            }
        }

        log.audit('resolveShipAddressLine miss', JSON.stringify({ customerId: customerId, shipTo: target, addressbook: debug }));
        return null;
    };

    return {
        post: post
    };
});
