(function($) {

    $.entwine('ss', function ($) {

        console.log('.....gggggggggg...')

        //hide the importer upload field on load
        $("div.csv-importer").entwine({
            onmatch: function() {
                this.hide();
            }
        });


        $('#action_importcsv').entwine({
            onclick: function(e){
                console.log('.....clicked...')
                $('div.csv-importer').entwine('.', function($){
                    this.toggle();
                });

            }
        });

        $(".import-upload-csv-field").entwine({
            //when file has uploaded, change url to the field mapper
            onmatch: function() {
                console.log('...importer matched...')
                this.on('fileuploaddone', function(e,data){
                    e.preventDefault();
                    console.log('....file uploaded....')
                    console.log(data)
                    // window.location.href = data.result[0].import_url;
                });
            }
        });


    });


})(jQuery);
