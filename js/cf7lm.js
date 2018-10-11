jQuery(function($){
    if( $('#lead-table').length > 0){
        $('#lead-table').jtable({
            title: 'Lead Management table',
            paging: true, //Enable paging
            pageSize: 10, //Set page size (default: 10)
            sorting: true, //Enable sorting
            defaultSorting: 'Submitted DESC', //Set default sorting
            gotoPageArea: 'combobox',
            actions: {
                listAction: cf7ml_ajax.cf7ml_ajax + '?action=ajax_get_leads',
                deleteAction: cf7ml_ajax.cf7ml_ajax + '?action=delete_lead',
                updateAction: cf7ml_ajax.cf7ml_ajax + '?action=edit_lead',
                createAction: cf7ml_ajax.cf7ml_ajax + '?action=create_lead',
                viewAction: cf7ml_ajax.cf7ml_ajax + '?action=ajax_get_leads',
                sendAction: cf7ml_ajax.cf7ml_ajax + '?action=send_lead'
            },
            fields: fields,
            loadingRecords: function(event, data) {
                 $('#lead-table .jtable').dragtable({
                     maxMovingRows:1,
                     dragaccept: '.jtable-column-header',
                     persistState: function(table) {
                         var order = new Array();
                       table.el.find('th.jtable-column-header').each(function(i) {
                             if(i < table.el.find('th.jtable-column-header').length) {
                                 order[i] = $(this).data('header');
                             }
                         });
                         $('#lead-table').jtable('saveColSorting', {
                             order:  order
                         });
                     }
                 });
            },
            formCreated: function (event, data) {
                $('input[name=form_id]').focus();
                $('.date').datepicker({
                    dateFormat: 'yy-mm-dd',
                    changeMonth: true,
                    changeYear: true
                });

                $('input[type=checkbox]').each(function() {
                    var value = $(this).val();
                    if(value == 1)
                    {
                        $('input[name="'+ $(this).attr('name') + '_hidden"]').attr('disabled', 'true');
                    }
                    else{
                        $('input[name="'+ $(this).attr('name') + '_hidden"]').removeAttr('disabled');
                    }
                });
            },
            recordsLoaded: function(event, data){
                var supervise = {}; 
                var indexthisEl = $('th[data-header="Email"]').index() + 1;
                console.log(indexthisEl);
                jQuery('.jtable  td:nth-child('+indexthisEl+')').each(function() { 
                var txt = jQuery(this).text();  
                console.log('работает не лезь!');
                if (supervise[txt])  {  
                jQuery(this).parent().css('background-color',"#FF9999"); 
                if(txt == ""){
                    console.log('вошел');
                    jQuery(this).parent().css('background-color',"transparent");
                }
                }
                
                else 
                supervise[txt] = true; 
                });

                var indexthisEl2 = $('th[data-header="Phone"]').index() + 1;
                console.log(indexthisEl2);
                var supervise2 = {}; 
                jQuery('.jtable  td:nth-child('+indexthisEl2+')').each(function() { 
                var txt2 = jQuery(this).text();  
                console.log('работает не лезь!');
                if (supervise2[txt2])  {  
                jQuery(this).parent().css('background-color',"#FF9999"); 
            
                if(txt2 === ""){
                    console.log('вошел');
                        jQuery(this).parent().css('background-color',"transparent");
                    }
                } 
                supervise2[txt2] = true; 
                });

            }
        });

        //Load student list from server
        $('#lead-table').jtable('load');

        $(document).tooltip();

        $('.date').datepicker({
            dateFormat: 'yy-mm-dd',
            changeMonth: true,
            changeYear: true
        });

        /* Shortcut buttons */
        $('.shortcut-buttons .button').live('click', function(){
            $('.shortcut-buttons .active').removeClass('active');
            $(this).addClass('active');
        });

        $('.today-button').live('click', function(){
            var date = new Date();
            var today = $.datepicker.formatDate('yy-mm-dd', date);
            $('input[name=start_date]').val(today)
            $('input[name=end_date]').val(today)
        });

        $('.this-month-button').live('click', function(){
            var date = new Date();
            var this_month = parseFloat(date.getMonth()) + 1 ;
            var this_year = date.getFullYear();

            var start_date = new Date(this_year +  ', ' + this_month + ', 1' );
            start_date = $.datepicker.formatDate('yy-mm-dd', start_date);

            var days_in_month = daysInMonth(this_month, this_year);
            var end_date = new Date(this_year +  ', ' + this_month + ', ' + days_in_month );
            end_date = $.datepicker.formatDate('yy-mm-dd', end_date);
            $('input[name=start_date]').val(start_date)
            $('input[name=end_date]').val(end_date)
        });

        $('.last-month-button').live('click', function(){
            var date = new Date();
            date.setDate(1);

            var last_month = date.getMonth();
			
			if (last_month == 0){
				last_month = 12;
			}
			
            var this_year = date.getFullYear();

            var start_date = new Date(this_year +  ', ' + last_month + ', 1' );
            start_date = $.datepicker.formatDate('yy-mm-dd', start_date);

            var days_in_month = daysInMonth(last_month, this_year);
            var end_date = new Date(this_year +  ', ' + last_month + ', ' + days_in_month );
            end_date = $.datepicker.formatDate('yy-mm-dd', end_date);
            $('input[name=start_date]').val(start_date)
            $('input[name=end_date]').val(end_date)
        });

        $('.this-year-button').live('click', function(){
            var date = new Date();
            var this_year = date.getFullYear();

            var start_date = new Date(this_year +  ', ' + '1, 1' );
            start_date = $.datepicker.formatDate('yy-mm-dd', start_date);

            var days_in_month = daysInMonth('12', this_year);
            var end_date = new Date(this_year +  ', ' + '12, ' + days_in_month );
            end_date = $.datepicker.formatDate('yy-mm-dd', end_date);
            $('input[name=start_date]').val(start_date)
            $('input[name=end_date]').val(end_date)
        });

        /* Leads Search */
        $('.filter-button').live('click', function(){
             $('#lead-table').jtable('load', {
                  search_text: $('input[name=search_text]').val(),
                  search_form_id: $('input[name=search_form_id]').val(),
                  start_date: $('input[name=start_date]').val(),
                  end_date: $('input[name=end_date]').val(),
                  boolean_field: $('select[name=search_boolean_field]').val(),
                  boolean_field_value: $('select[name=search_boolean_value]').val(),
                  numeric_field: $('select[name=search_numeric_field]').val(),
                  bigger_numeric_value: $('input[name=bigger_numeric_value]').val(),
                  small_numeric_value: $('input[name=small_numeric_value]').val()
            });
        });

        /* Reset Search */
        $('.reset-button').live('click', function(){
             $('#lead-table').jtable('load');
             $('#lead-search-form')[0].reset();
            $('.shortcut-buttons .active').removeClass('active');
        });
    }

    if( $('#fields-table').length > 0){
        $('#fields-table').jtable({
            title: 'Lead Management table',
            paging: true, //Enable paging
            pageSize: 10, //Set page size (default: 10)
            sorting: true, //Enable sorting
            defaultSorting: 'id DESC', //Set default sorting,
            gotoPageArea: 'none',

            actions: {
                listAction: cf7ml_ajax.cf7ml_ajax + '?action=ajax_get_fields',
                deleteAction: cf7ml_ajax.cf7ml_ajax + '?action=delete_field',
                updateAction: cf7ml_ajax.cf7ml_ajax + '?action=edit_field',
                createAction: cf7ml_ajax.cf7ml_ajax + '?action=create_field',
                viewAction: cf7ml_ajax.cf7ml_ajax + '?action=ajax_get_fields',
                sendAction: cf7ml_ajax.cf7ml_ajax + '?action=send_lead'
            },
            messages: {
                loadingMessage: 'Loading fields...',
                noDataAvailable: 'No data available!',
                addNewRecord: 'Add new field',
                editRecord: 'Edit Field',
                areYouSure: 'Are you sure?',
                deleteConfirmation: 'This field will be deleted. Are you sure?'
            },
            fields: {
                id: {
                    key: true,
                    list: false
                },
                field_label: {
                    title: 'Field Label',
                    width: '30%'
                },
                field_name: {
                    title: 'Field Name',
                    width: '25%',
                    edit: false
                },
                type: {
                    title: 'Type',
                    width: '20%',
                    options: { 'alphanumeric': 'Alphanumeric', 'integers': 'Integers', 'boolean': 'Boolean' }
                },
                default_value: {
                    title: 'Default Value',
                    width: '20%',
                    inputTitle: 'Default Value <br/> <span style="font-size:9px">Insert 0 or leave empty for false, insert 1 for true value</span>'
                }
            }
        });

        //Load student list from server
        $('#fields-table').jtable('load');
    }


    $('input[name=field_label]').live('change', function(){
        var label = $(this);
        var val = label.val().toLowerCase().split(' ').join('_').split('\'').join('');
        $(this).closest('#jtable-create-form').find('input[name=field_name]').val(val);
    });

    $('input[type=checkbox]').live('change', function(){
        var value = $(this).val();

        if(value == 1)
        {
            $('input[name="'+ $(this).attr('name') + '_hidden"]').removeAttr('disabled');
        }
        else{
            $('input[name="'+ $(this).attr('name') + '_hidden"]').attr('disabled', 'true');
        }

    });

    $('.match-fields select').live('change', function(){
        var  selector = $(this).attr('class').replace('select_', '');
        $('.' + selector).val($(this).val())

    });

    $('.export-container .export').live('click', function(){
        search_text = $('input[name=search_text]').val();
        start_date = $('input[name=start_date]').val();
        end_date = $('input[name=end_date]').val();
        boolean_field = $('select[name=search_boolean_field]').val();
        boolean_field_value = $('select[name=search_boolean_value]').val();
        numeric_field = $('select[name=search_numeric_field]').val();
        bigger_numeric_value = $('input[name=bigger_numeric_value]').val();
        small_numeric_value =  $('input[name=small_numeric_value]').val();

        var url = cf7ml_ajax.cf7ml_ajax + '?action=ajax_export&search_text='+search_text+'&start_date='+start_date+'&end_date='+end_date+'&boolean_field='+boolean_field+'&boolean_field_value='+boolean_field_value+'&numeric_field='+numeric_field+'&bigger_numeric_value='+bigger_numeric_value+'&small_numeric_value='+small_numeric_value;
        location.href = url;

    });

    $('#save-cf7-names').live('click', function(){
        var fields_names = [];
        $('input[name^=cf7_name]').each(function( index ) {
            fields_names.push($(this).val())
        });

        var data = {
            action: 'save_cf7_fields_names',
            id: $('input[name=post_ID]').val(),
            fields_names: fields_names
        };

        var this_spinner = spinner.clone();

       $(this).after(this_spinner.show());

        $.ajax({
            type: 'POST',
            url: cf7ml_ajax.cf7ml_ajax,
            data: data,
            success: function(response)
            {
                $('input[name^=cf7_name]').each(function( index ) {
                    $(this).val('');
                });
                $('.in-db').html(response);

                this_spinner.hide();
            }
        });

    });

});

function addFieldName() {
    jQuery('#cf7-fields').append('<p><input type="text"  name="cf7_name[]" value="" /></p>');
    return false;
}

function daysInMonth(month,year) {
    return new Date(year, month, 0).getDate();
}