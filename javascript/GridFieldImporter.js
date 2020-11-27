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


        $('#csvupload').on('change', function() {

            let url = $(".toggle-csv-fields").data("url");
            let file_data = $(this).prop('files')[0];
            let form_data = new FormData();
            form_data.append('file', file_data);

            $.ajax({
                url: url + '/' + 'importer/saveInto', // point to server-side PHP script
                dataType: 'text',  // what to expect back from the PHP script, if anything
                cache: false,
                contentType: false,
                processData: false,
                data: form_data,
                type: 'post',
                success: function(data){
                    const parsed = JSON.parse(data);
                    window.location.href = parsed[0].import_url;
                }
            });
        });


    });


})(jQuery);
