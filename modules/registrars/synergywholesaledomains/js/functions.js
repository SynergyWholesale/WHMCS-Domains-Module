/**
 * Synergy Wholesale Registrar Module
 *
 * @copyright Copyright (c) Synergy Wholesale Pty Ltd 2020
 * @license https://github.com/synergywholesale/whmcs-domains-module/LICENSE
 */
 if ('undefined' !== typeof toastr) {
    toastr.options.positionClass = 'toast-top-full-width';
    toastr.options.extendedTimeOut = 0;
    toastr.options.timeOut = 1e4;
    toastr.options.fadeOut = 500;
    toastr.options.fadeIn = 500;
}

$(document).ready(function () {
    $('#dnsrecords').delegate('select', 'change', function () {
        var self = $(this);
        var form = self.closest('form');
        var addressCol = form.find("div:eq(1)");
        
        if (self.val() == 'SRV') {
            addressCol.html('\
                <input type="number" min="0" max="65535" name="address1" placeholder="Weight" size="2" />\
                <input type="number" min="0" max="65535" name="address2" placeholder="Port" size="2" />\
                <input type="text" name="address3" placeholder="Target" size="8" />\
            ')
        } else {
            addressCol.html('<input type="text" name="address" size="20" />')
        }
    });
});

function Toast(type, css, msg) {
    toastr.options.positionClass = css;
    toastr[type](msg);
}

function formSubmitDNS() {
    let type = determineDNSType(parseInt(document.getElementById('option').value));
    let text = `Are you sure you want to change the DNS Type to ${type}?`;
    if (confirm(text)) {
        this.form.submit()
    }
}

function determineDNSType(type) {
    const dnsTypes = {
        0: 'Inactive',
        1: 'Custom Nameservers',
        2: 'URL & Email Forwarding + DNS Hosting',
        3: 'Parked',
        4: 'DNS Hosting',
        5: 'Default Nameservers',
        6: 'Legacy Hosting Nameservers',
        7: 'Wholesale Hosting Nameservers',
    }

    return dnsTypes[type];
}
// --------------------------------------------------
// Any Email Record related functionality starts here
// --------------------------------------------------
// List email records
function listMailRecords(domain_id) {

    let url = `clientarea.php?action=domaindetails&id=${domain_id}&modop=custom&a=manageEmailForwarding&op=getRecords`
    let promise = executeAJAXRequest('POST', url, '', 1e4).fail(errorHandler);

    promise.done(function(data) {
        if ('undefined' !== typeof data.error) {
            return;
        }
        
        $('.sw-loader').fadeOut('fast');
        for (i = 0; i < data.length; i++) {
            populateEmailRow(data[i].record_id, data[i].prefix, data[i].forward_to);
        }

        Toast('success', 'toast-top-right', 'Successfully retrieved Email Forwards');
    });
}

// Add an email record
function addEmailRecord(domain_id, temprecord_id, formdata) {

    let url = `clientarea.php?action=domaindetails&id=${domain_id}&modop=custom&a=manageEmailForwarding&op=addRecord`;
    let promise = executeAJAXRequest('POST', url, formdata, 1e4).fail(errorHandler);

    promise.done(function(data) {
        if ('undefined' !== typeof data.error) {
            return;
        }

        $('#emailrow-' + temprecord_id).attr('id', 'emailrow-' + data.record_id);
        $('#emailform-' + temprecord_id).attr('id', 'emailform-' + data.record_id);
        $('#newemailrecord_id-' + temprecord_id).attr('id', 'emailrecord_id-' + data.record_id);
        $('#emailrecord_id-' + data.record_id).val(data.record_id);

        Toast('success', 'toast-top-right', 'Email forwarder successfully added');
    });
}

// Delete email record
function deleteEmailRecord(domain_id, record_id, formdata) {

    let url = `clientarea.php?action=domaindetails&id=${domain_id}&modop=custom&a=manageEmailForwarding&op=deleteRecord`;
    let promise = executeAJAXRequest('POST', url, formdata, 1e4).fail(errorHandler);

    promise.done(function(data) {
        if ('undefined' !== typeof data.error) {
            return;
        }
        
        $('#emailrow-' + record_id).remove();
        
        Toast('success', 'toast-top-right', 'EMail Forwarder successfully deleted');
    });
}

// Update email forwarder
function saveEmailRecord(domain_id, record_id, formdata) {
    
    let url = `clientarea.php?action=domaindetails&id=${domain_id}&modop=custom&a=manageEmailForwarding&op=`;
    let promise = executeAJAXRequest('POST', url + 'deleteRecord', formdata, 1e4).fail(errorHandler);

    promise.done(function(data) {
        if ('undefined' === typeof data.error) {
            return;
        }
        
        let promise = executeAJAXRequest('POST', url + 'addRecord', formdata, 1e4).fail(errorHandler);
        promise.done(function(data) {
            if ('undefined' !== typeof data.error) {
                return;
            }

            $('#emailrow-' + record_id).attr('id', 'emailrow-' + data.record_id);
            $('#emailform-' + record_id).attr('id', 'emailform-' + data.record_id);
            $('#newemailrecord_id-' + record_id).attr('id', 'emailrecord_id-' + data.record_id);
            $('#emailrecord_id-' + data.record_id).val(data.record_id);

            Toast('success', 'toast-top-right', 'Successfully updated Email Forwarder');
        });
    });
}

// Populate the email row
function populateEmailRow(record_id, prefix, forward_to) {
    $('#emailforwards').append(`
        <div class="sw-table-form" id="emailrow-${record_id}">
            <form class="row" id="emailform-${record_id}">
                <input type="hidden" name="record_id" id="emailrecord_id-${record_id}" value="${record_id}">
                <div class="col-lg-4">
                    <input type="text" name="prefix" size="30" value="${prefix}" />
                </div>
                <div class="col-lg-2 text-center"><i class="fa fa-long-arrow-right fa-align-center fa-2x"></i></div>
                <div class="col-lg-4">
                    <input type="text" name="forwardto" size="30" value="${forward_to}" />
                </div>
                <div class="col-lg-2 text-center">
                    <div class="btn-group" role="group">
                        <button type="button" class="btn btn-danger delete-row"><span class="fas fa-trash-alt"></span></button>
                        <button type="button" class="btn btn-success save-row"><span class="fas fa-check"></span></button>
                    </div>
                </div>
            </form>
        </div>
    `);
}

// --------------------------------------------------
// Any DNS / URL Record related functionality starts here
// --------------------------------------------------
// List DNS / URL records
function listRecords(domain_id) {

    let url = `clientarea.php?action=domaindetails&id=${domain_id}&modop=custom&a=manageDNSURLForwarding&op=getRecords`;
    let promise = executeAJAXRequest('POST', url).fail(errorHandler);

    promise.done(function(data) {
        if ('undefined' !== typeof data.error) {
            return;
        }

        $('.sw-loader').fadeOut('fast');
        data.records.forEach(function(record) {
            if ('URL' === record.type || 'FRAME' === record.type) {
                populateURLRow(record.record_id, record.hostname, record.type, record.address);
            } else {
                populateDNSRow(record.record_id, data.domain, record.hostname, record.type, record.ttl, record.address, record.priority);
            }
        });
        
        Toast('success', 'toast-top-right', 'Successfully retrieved DNS / URL records');
    });

}

// Add DNS / URL Record
function addRecord(domain_id, temprecord_id, formdata, recordType) {

    if ('undefined' === typeof recordType) {
        recordType = 'dns';
    }

    let url = `clientarea.php?action=domaindetails&id=${domain_id}&modop=custom&a=manageDNSURLForwarding&op=addRecord`;
    let promise = executeAJAXRequest('POST', url, formdata, 1e4).fail(errorHandler);

    promise.done(function(data) {
        if ('undefined' !== typeof data.error) {
            return;
        }

        if (recordType == 'dns') {
            $('#row-' + temprecord_id).attr('id', 'row-' + data.record_id);
            $('#form-' + temprecord_id).attr('id', 'form-' + data.record_id);
            $('#newrecord_id-' + temprecord_id).attr('id', 'record_id-' + data.record_id);
            $('#record_id-' + data.record_id).val(data.record_id);
            $('#form-' + data.record_id + ' input[name=hostname]').val(data.recordName);
            $('#form-' + data.record_id + ' input[name=address]').val(data.recordContent);
            $('#form-' + data.record_id + ' input[name=ttl]').val(data.recordTTL);

            if ('undefined' !== typeof data.recordPrio && ('MX' === data.recordType || 'SRV' === data.recordType)) {
                $('#form-' + data.record_id + ' input[name=priority]')
                    .prop('disabled', false)
                    .val(data.recordPrio);
            } else {
                $('#form-' + data.record_id + ' input[name=priority]')
                    .prop('disabled', true)
                    .val('');
            }

            Toast('success', 'toast-top-right', 'Successfully added DNS record');
        } else if (recordType == 'url') {
            $('#urlrow-' + temprecord_id).attr('id', 'urlrow-' + data.record_id);
            $('#urlform-' + temprecord_id).attr('id', 'urlform-' + data.record_id);
            $('#urlnewrecord_id-' + temprecord_id).attr('id', 'urlrecord_id-' + data.record_id);
            $('#urlrecord_id-' + data.record_id).val(data.record_id);
            $('#urlform-' + data.record_id  + ' input[name=hostname]').val(data.hostname);
            $('#urlform-' + data.record_id  + ' input[name=address]').val(data.url);
            
            Toast('success', 'toast-top-right', 'Successfully added URL record');
        }
    });
}

// Delete DNS / URL Record
function deleteRecord(domain_id, record_id, formdata, recordType) {

    let url = `clientarea.php?action=domaindetails&id=${domain_id}&modop=custom&a=manageDNSURLForwarding&op=deleteRecord`;
    let promise = executeAJAXRequest('POST', url, formdata, 1e4).fail(errorHandler);

    promise.done(function(data) {
        if ('undefined' !== typeof data.error) {
            return;
        }

        if ('dns' === recordType) {
            $('#row-' + record_id).remove();
            Toast('success', 'toast-top-right', 'Successfully deleted DNS record');
        } else if ('url' === recordType) {
            $('#urlrow-' + record_id).remove();
            Toast('success', 'toast-top-right', 'Successfully deleted URL record');
        }
    });
}

// Save DNS / URL Record
function saveRecord(domain_id, record_id, formdata, recordType) {

    if ('undefined' === typeof recordType) {
        recordType = 'dns';
    }

    let url = `clientarea.php?action=domaindetails&id=${domain_id}&modop=custom&a=manageDNSURLForwarding&op=`;


    if (recordType == 'dns') {
        // Execute the AJAX Call to delete record
        let promise = executeAJAXRequest('POST', url + 'deleteRecord', formdata).fail(errorHandler);
        promise.done(function(data) {
            if ('undefined' !== typeof data.error) {
                return;
            }

            let promise = executeAJAXRequest('POST', url + 'addRecord', formdata).fail(errorHandler);
            promise.done(function(data) {
                if ('undefined' !== typeof data.error) {
                    return;
                }

                $('#row-' + record_id).attr('id', 'row-' + data.record_id);
                $('#form-' + record_id).attr('id', 'form-' + data.record_id);
                $('#record_id-' + record_id).attr('id', 'record_id-' + data.record_id);
                $('#record_id-' + data.record_id).val(data.record_id);
                $('#form-' + data.record_id + ' input[name=hostname]').val(data.recordName);
                $('#form-' + data.record_id + ' input[name=address]').val(data.recordContent);
                $('#form-' + data.record_id + ' input[name=ttl]').val(data.recordTTL);
                if (typeof data.recordPrio !== 'undefined' && (data.recordType == 'MX' || data.recordType == 'SRV')) {
                    $('#form-' + data.record_id + ' input[name=priority]')
                        .prop('disabled', false)
                        .val(data.recordPrio);
                } else {
                    $('#form-' + data.record_id + ' input[name=priority]')
                        .prop('disabled', true)
                        .val('');
                }
                Toast('success', 'toast-top-right', 'Successfully updated dns record');
            });
        });
    } else if (recordType == 'url') {
        // Execute the AJAX Call to delete record
        var promise = executeAJAXRequest('POST', url + 'deleteRecord', formdata).fail(errorHandler);
        promise.done(function(data) {
            if (data.error !== undefined) {
                return;
            }
            // Execute the AJAX Call to add record
            var promise = executeAJAXRequest('POST', url + 'addRecord', formdata).fail(errorHandler);
            promise.done(function(data) {
                if (data.error !== undefined) {
                    return;
                }
                $('#urlrow-' + record_id).attr('id', 'urlrow-' + data.record_id);
                $('#urlrow-' + record_id).attr('id', 'urlform-' + data.record_id);
                $('#urlrecord_id-' + record_id).attr('id', 'urlrecord_id-' + data.record_id);
                $('#urlrecord_id-' + data.record_id).val(data.record_id);
                $('#urlrow-' + data.record_id  + ' input[name=hostname]').val(data.hostname);
                $('#urlrow-' + data.record_id  + ' input[name=address]').val(data.url);
                Toast('success', 'toast-top-right', 'Successfully updated url record');
            });
        });
    }
}

function populateDNSRow(record_id, domain, hostname, type, ttl, address, priority) {
    let types = ['A','AAAA','MX','CNAME','TXT','SRV','NS'];
    let options = '', buttons = '', controls = '';

    types.forEach(function(value) {
        if (value == type) {
            options += `<option value="${value}" selected>${value}</option>`;
        } else {
            options += `<option value="${value}">${value}</option>`;
        }
    });

    if ('undefined' === typeof priority) {
        priority = '';
    }

    if ('NS' !== type || ('NS' == type && hostname !== domain)) {
        controls = `<div class="col-lg-2 text-center">
            <div class="btn-group" role="group">
                <button type="button" class="btn btn-danger delete-row"><span class="fas fa-trash-alt"></span></button>
                <button type="button" class="btn btn-success save-row"><span class="fas fa-check"></span></button>
            </div>
        </div>`;
    }

    var template = `<div class="sw-table-form" id="row-${record_id}">
        <form class="row" id="form-${record_id}">
            <input type="hidden" name="record_id" id="record_id-${record_id}" value="${record_id}">
            <div class="col-lg-3">
                <input type="text" name="hostname" size="20" value="${hostname}" />
            </div>
            <div class="col-lg-3">
                <input type="text" name="address" size="20" value="${address}" />
            </div>
            <div class="col-lg-2">
                <select name="type">${options}</select>
            </div>
            <div class="col-lg-1">
                <input type="number" min="300" max="99999" name="ttl" size="5" value="${ttl}" />
            </div>
            <div class="col-lg-1">
                <input type="number" min="0" max="65535" name="priority" size="3" value="${priority}" ${!['MX', 'SRV'].includes(type) ? 'disabled' : ''} />
            </div>
            ${controls}
        </form>
    </div>`;

    if ('SRV' == type) {
        var srvContent = address.split(" ");
        var template = `<div class="sw-table-form" id="row-${record_id}">
        <form class="row" id="form-${record_id}">
            <input type="hidden" name="record_id" id="record_id-${record_id}" value="${record_id}">
            <div class="col-lg-3">
                <input type="text" name="hostname" value="${hostname}" />
            </div>
            <div class="col-lg-3">
                <input type="number" min="0" max="65535" name="address1" placeholder="Weight" size="2" value="${srvContent[0]}" />
                <input type="number" min="0" max="65535" name="address2" placeholder="Port" size="2" value="${srvContent[1]}" />
                <input type="text" name="address3" placeholder="Target" size="8" value="${srvContent[2]}" />
            </div>
            <div class="col-lg-2">
                <select name="type">${options}</select>
            </div>
            <div class="col-lg-1">
                <input type="number" min="300" max="99999" name="ttl" value="${ttl}" />
            </div>
            <div class="col-lg-1">
                <input type="number" min="0" max="655355" name="priority" value="${priority}" />
            </div>
            ${controls}
        </form>
    </div>`;
    }

    $('#dnsrecords').append(template);
}

function populateURLRow(record_id, hostname, type, address) {
    let types = {
        URL: 'URL Forward',
        FRAME: 'URL Frame (Cloaking)'
    };

    let options = '';
    
    for (i in types) {
        if (i == type) {
            options += `<option value="${i}" selected>${types[i]}</option>`;
        } else {
            options += `<option value=${i}">${types[i]}</option>`;
        }
    }

    $('#urlforwards').append(`
        <div class="sw-table-form" id="urlrow-${record_id}">
            <form class="row" id="urlform-${record_id}">
                <input type="hidden" name="record_id" id="urlrecord_id-${record_id}" value="${record_id}">
                <div class="col-lg-3">
                    <input type="text" name="hostname" size="20" value="${hostname}"/>
                </div>
                <div class="col-lg-3">
                    <input type="text" name="address" size="20" value="${address}"/>
                </div>
                <div class="col-lg-3">
                    <select name="type">${options}</select>
                </div>
                <div class="col-lg-2 text-center">
                    <div class="btn-group" role="group">
                        <button type="button" class="btn btn-danger delete-row"><span class="fas fa-trash-alt"></span></button>
                        <button type="button" class="btn btn-success save-row"><span class="fas fa-check"></span></button>
                    </div>
                </div>
            </form>
        </div>
    `);
}

// Execute the AJAX request
function executeAJAXRequest(type, url, data, timeout) {
    if (timeout == undefined) {
        timeout = 15e3;
    }

    if (type == undefined) {
        type = 'GET';
    }
    // Make the AJAX request
    return $.ajax({
        type: type,
        url: url,
        data: data,
        timeout: timeout,
        dataType: 'text json'
    }).done(stdSuccessCB);
}

// This callback is used to detect and JSON errors, etc
function stdSuccessCB(result) {
    if (typeof result === 'undefined') {
        Toast('error', 'toast-top-right', 'Unable to parse JSON Response');
    } else {
        if (typeof result.error !== 'undefined') {
            Toast('error', 'toast-top-right', result.error);
        }
    }
}

function errorHandler(jqXHR, textStatus, errorThrown) {
    console.log(jqXHR);
    console.log(textStatus);
    console.log(errorThrown);
}

function EmailForwardPageReady(domain_id) {
    // Set up our loading animation
    $(document).on({
        ajaxStart: function() {
            $('button').attr('disabled', 'disabled');
        },
        ajaxStop: function() {
            $('button').removeAttr('disabled');
        }
    });

    // Retrieve a list of all currently configured records
    listMailRecords(domain_id);
    
    // If we get a click on the class sw-insert-row do the following
    $(document).on('click', '.sw-insert-row', function() {
        // Count the number of div's in the emailforwards div
        let row_count = $('#emailforwards > div').length + 1;

        // Append the following html to the emailforwards div
        $('#emailforwards').append(`
            <div class="sw-table-form" id="emailrow-${row_count}">
                <form class="row" id="emailform-${row_count}">
                    <input type="hidden" name="record_id" id="newemailrecord_id-${row_count}" value="${row_count}" />
                    <div class="col-lg-4">
                        <input type="text" name="prefix" size="30" placeholder="eg. info without the @domain" />
                    </div>
                    <div class="col-lg-2 text-center">
                        <i class="fa fa-long-arrow-right fa-align-center fa-2x"></i>
                    </div>
                    <div class="col-lg-4">
                        <input type="text" name="forwardto" size="30" placeholder="Email address to forward to" />
                    </div>
                    <div class="col-lg-2 text-center">
                        <div class="btn-group" role="group">
                            <button type="button" class="btn btn-danger delete-row"><span class="fas fa-trash-alt"></span></button>
                            <button type="button" class="btn btn-success save-row"><span class="fas fa-check"></span></button>
                        </div>
                    </div>
                </form>
            </div>
        `);
    });

    function saveRow(record_id) {
        // Get the new record id if it exists
        let new_record = $('#newemailrecord_id-' + record_id).val();
        // Serialize the form
        let serializedData = $('#emailform-' + record_id).serialize();
        // See if newrecord is defined
        if ('undefined' === typeof new_record) {
            // Save the record instead of adding it
            return saveEmailRecord(domain_id, record_id, serializedData);
        }

        // Add the record
        return addEmailRecord(domain_id, new_record, serializedData);    
    }

    // If we get a click on the class save-row do the following
    $(document).on('click', '.save-row', function() {
        // Get the record id from the hidden input field
        let record_id = $(this).parent().parent().parent().find('input[type=hidden]').val();
        return saveRow(record_id);
    });

    $(document).on('keypress', '.row > form > div > input', function (event) {
        if (13 === event.keyCode) {
            // Get the record id from the hidden input field
            let record_id = $(this).parent().parent().find('input[type=hidden]').val();
            return saveRow(record_id);
        }
    });


    // If we get a click on the class delete-row do the following
    $(document).on('click', '.delete-row', function() {
        // Get the record id from the hidden input field
        let recordData = $(this).parent().parent().parent().parent().find('input[type=hidden]');
        if (recordData.attr('id').indexOf('new') !== -1) {
            recordData.parent().parent().parent().hide();
            return;
        }

        let record_id = recordData.val();
        // Serialize the form
        let serializedData = $('#emailform-' + record_id).serialize();
        // Add the record
        deleteEmailRecord(domain_id, record_id, serializedData);
        return;
    });
}

function DnsUrlPageReady(domain_id) {
    // Set up our loading animation
    $(document).on({
        ajaxStart: function() {
            $('button').attr('disabled', 'disabled');
        },
        ajaxStop: function() {
            $('button').removeAttr('disabled');
        }
    });
    // Retrieve a list of all currently configured records
    listRecords(domain_id);
    // If we get a click on the class sw-insert-row do the following
    $(document).on('click', '.sw-insert-row', function() {
        // Work out which div element we should be adding to
        let section = $(this).attr('data-append');
        let row_count = 0;
        switch (section) {
            case 'dnsrecords':
                // Count the number of div's in the dnsrecords div
                row_count = $('#dnsrecords > div').length + 1;
                // Append the following html to the dnsrecords div
                $('#dnsrecords').append(`
                    <div class="sw-table-form" id="row-${row_count}">
                        <form class="row" id="form-${row_count}">
                            <input type="hidden" name="record_id" id="newrecord_id-${row_count}" value="${row_count}">
                            <div class="col-lg-3">
                                <input type="text" name="hostname" />
                            </div>
                            <div class="col-lg-3">
                                <input type="text" name="address" />
                            </div>
                            <div class="col-lg-2">
                                <select name="type">
                                    <option value="A">A</option>
                                    <option value="AAAA">AAAA</option>
                                    <option value="CNAME">CNAME</option>
                                    <option value="MX">MX</option>
                                    <option value="SRV">SRV</option>
                                    <option value="TXT">TXT</option>
                                    <option value="NS">NS</option>
                                </select>
                            </div>
                            <div class="col-lg-1">
                                <input type="number" min="300" max="99999" name="ttl" size="5" />
                            </div>
                            <div class="col-lg-1">
                                <input type="number" min="0" max="65535" name="priority" size="3" />
                            </div>
                            <div class="col-lg-2 text-center">
                                <div class="btn-group" role="group">
                                    <button type="button" class="btn btn-danger delete-row"><span class="fas fa-trash-alt"></span></button>
                                    <button type="button" class="btn btn-success save-row"><span class="fas fa-check"></span></button>
                                </div>
                            </div>
                        </form>
                    </div>
                `);
                break;
            case 'urlforwards':
                row_count = $('#urlforwards > div').length + 1;

                $('#urlforwards').append(`
                    <div class="sw-table-form" id="urlrow-${row_count}">
                        <form class="row" id="urlform-${row_count}">
                            <input type="hidden" name="record_id" id="urlnewrecord_id-${row_count}" value="${row_count}">
                            <div class="col-lg-3">
                                <input type="text" name="hostname" size="20" />
                            </div>
                            <div class="col-lg-3">
                                <input type="text" name="address" size="20" />
                            </div>
                            <div class="col-lg-3">
                                <select name="type">
                                    <option value="URL">URL Forward</option>
                                    <option value="FRAME">URL Frame (Cloaking)</option>
                                </select>
                            </div>
                            <div class="col-lg-2 text-center">
                                <div class="btn-group" role="group">
                                    <button type="button" class="btn btn-danger delete-row"><span class="fas fa-trash-alt"></span></button>
                                    <button type="button" class="btn btn-success save-row"><span class="fas fa-check"></span></button>
                                </div>
                            </div>
                        </form>
                    </div>
                `);
                break;
        }
    });

    function saveRow(section, record_id) {
        
        let serializedData;

        switch (section) {
            case 'dnsrecords':
                new_record = $('#newrecord_id-' + record_id).val();
                serializedData = $('#form-' + record_id).serialize();

                if ('undefined' === typeof new_record) {
                    saveRecord(domain_id, record_id, serializedData, 'dns');
                    break;
                }

                addRecord(domain_id, new_record, serializedData, 'dns');
                break;
            case 'urlforwards':
                new_record = $('#urlnewrecord_id-' + record_id).val();
                serializedData = $('#urlform-' + record_id).serialize();

                if ('undefined' === typeof new_record) {
                    saveRecord(domain_id, record_id, serializedData, 'url');
                    break;
                }
                
                addRecord(domain_id, record_id, serializedData, 'url');
                break;
        }
 
    }

    // If we get a click on the class save-row do the following
    $(document).on('click', '.save-row', function() {
        let section = $(this).parent().parent().parent().parent().parent().attr('id');
        let record_id = $(this).parent().parent().parent().find('input[type=hidden]').val();
        return saveRow(section, record_id);
    });

    $(document).on('keypress', '.row > form > div > input', function (event) {
        if (13 === event.keyCode) {
            let section = $(this).parent().parent().parent().parent().attr('id');
            let record_id = $(this).parent().parent().find('input[type=hidden]').val();
            return saveRow(section, record_id);
        }
    });

    // If we get a click on the class delete-row do the following
    $(document).on('click', '.delete-row', function() {

        let section = $(this).parent().parent().parent().parent().parent().attr('id');
        let record = $(this).parent().parent().parent().find('input[type=hidden]');
        let record_id = record.val();
        let serializedData;

        switch (section) {
            case 'dnsrecords':
                serializedData = $('#form-' + record_id).serialize();
                if (-1 !== record.attr('id').indexOf('new')) {
                    $(this).parent().parent().parent().parent().hide();
                    break;
                }

                deleteRecord(domain_id, record_id, serializedData, 'dns');
                break;
            case 'urlforwards':
                serializedData = $('#urlform-' + record_id).serialize();
                if (-1 !== record.attr('id').indexOf('new')) {
                     $(this).parent().parent().parent().parent().hide();
                    break;
                }
                
                deleteRecord(domain_id, record_id, serializedData, 'url');
                break;
        }
    });
}
