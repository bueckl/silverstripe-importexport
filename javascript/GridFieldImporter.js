(function($) {

    $.entwine('ss', function ($) {

        //hide the importer upload field on load
        $("div.csv-importer").entwine({
            onmatch: function() {
                this.hide();
            }
        });


        $('#action_importcsv').entwine({
            onclick: function(e){
                $('div.csv-importer').entwine('.', function($){
                    this.toggle();
                });

            }
        });


        // $('#csvupload').on('change', function() {

        //     let url = $(".toggle-csv-fields").data("url");
        //     let file_data = $(this).prop('files')[0];
        //     let form_data = new FormData();
        //     form_data.append('file', file_data);

        //     $.ajax({
        //         url: url + '/' + 'importer/saveInto', // point to server-side PHP script
        //         dataType: 'text',  // what to expect back from the PHP script, if anything
        //         cache: false,
        //         contentType: false,
        //         processData: false,
        //         data: form_data,
        //         type: 'post',
        //         success: function(data){                    
        //             const parsed = JSON.parse(data);
        //             window.location.href = parsed[0].import_url;
        //         }
        //     });
        // });

        $('#csvupload').on('change', function() {

            let url = $(".toggle-csv-fields").data("url");
            let file_data = $(this).prop('files')[0];
            
            // Check if a file is selected
            if (!file_data) {
                alert("Please select a file.");
                return;
            }
        
            // Check if the selected file is a CSV
            if (file_data.type !== 'text/csv') {
                alert("Please upload a CSV file.");
                return;
            }
        
            let form_data = new FormData();
            form_data.append('file', file_data);
            //console.log(form_data);
            $.ajax({
                url: url + '/importer/saveInto', // point to server-side PHP script
                dataType: 'text',  // what to expect back from the PHP script, if anything
                cache: false,
                contentType: false,
                processData: false,
                data: form_data,
                type: 'POST',
                success: function(data){
                    try {
                        const parsed = JSON.parse(data);
                        if (parsed && parsed[0] && parsed[0].import_url) {
                            window.location.href = parsed[0].import_url;
                        } else {
                            alert("Unexpected response format.");
                        }
                    } catch (e) {
                        alert("Failed to parse response.");
                    }
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    alert("Error: " + textStatus + " - " + errorThrown);
                }
            });
        });
        


    });


})(jQuery);
