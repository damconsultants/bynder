require(['jquery'], function ($) {
    $('#add_bynder_button').on('click', function () {
        BynderCompactView.open({
            language: "en_US",
            theme: {
                colorButtonPrimary: "#3380FF"
            },
            mode: "SingleSelectFile",
            onSuccess: function (assets, additionalInfo) {
                
                defaultDomain: "media.bestreviews.com",
                        importedAssetsContainer = document.getElementById("importedAssets");
                importedAssetsContainer.innerHTML = "";
                //console.log(assets)
                var asset = assets[0];
                //console.log(asset);
                jQuery.ajax({
                    type: "POST",
                    url: 'https://byndermagento.tk/upload_images.php?form_key=' + formKey,
                    data: asset,
                    crossDomain: true,
                    dataType: 'json',
                    beforeSend: function () {
                        jQuery('.loading-mask').show();
                    },
                    error: function () {
                        alert("Something went wrong!!!");
                        jQuery('.loading-mask').hide();
                    },
                    success: function (res) {
                        jQuery('.loading-mask').hide();
                        jQuery('#YnluZGVy a').click();
                        alert(res.msg);
                    }
                });
            }
        });
    });

});