if(!window.jQuery) {console.log("RadicalForm: There is no jQuery library!")}
jQuery(document).ready(function () {

    var uniq = (new Date).getTime() + Math.floor(Math.random()*100); // get uniq id for upload a file

    jQuery(".rf-button-send").after('<input type="hidden" name="uniq" value="'+uniq+'" />')
                             .after(rfToken)
                            .after('<input type="hidden" name="url" value="'+window.location.href+'" />')
                            .after('<input type="hidden" name="resolution" value="'+screen.width +'x' + screen.height+'" />')
                            .after('<input type="hidden" name="reffer" value="'+document.referrer+'" />');

    var temp=RadicalForm.DangerClass.split(" ");
    RadicalForm.DangerClasses=temp.join(".");
    jQuery("body").on("keypress","form input."+RadicalForm.DangerClasses, function (e) {
        jQuery(this).removeClass(RadicalForm.DangerClass);
    })
        .on("keypress","form textarea."+RadicalForm.DangerClasses, function (e) {
        jQuery(this).removeClass(RadicalForm.DangerClass);
    })
        .on("change","form select."+RadicalForm.DangerClasses, function (e) {
        jQuery(this).removeClass(RadicalForm.DangerClass);
    });

    // file upload
    jQuery("input[type='file'].rf-upload-button").on("change", function (e) {
            if(!jQuery(this).attr("name")) {console.log("RadicalForm: There is no 'name' attribute for rf-upload-button!"); return; }

            var textForUploadButton = jQuery(this).siblings('.rf-upload-button-text')[0],
                tmp = jQuery(textForUploadButton).html(),
                formData = new FormData();
            formData.append(this.name, this.files[0]);
            if(this.files[0].size<RadicalForm.MaxSize) {
                formData.append("uniq", uniq);

                jQuery(textForUploadButton).html(RadicalForm.waitingForUpload)
                    .prop('disabled', true);

                jQuery.ajax({
                    url: RadicalForm.Base+'/index.php?option=com_ajax&plugin=radicalform&format=json&group=system&file=1&size=' + this.files[0].size,
                    type: 'post',
                    contentType: false,
                    processData: false,
                    dataType: "json",
                    data: formData,
                    complete: function (json, status) {

                        jQuery(textForUploadButton).html(tmp)
                            .prop('disabled', false);

                        if ("error" in json.responseJSON) {
                            jQuery('.rf-filenames-list').append("<div>" + json.responseJSON.error + "</div>"); // add the name of file
                        } else {
                            jQuery('.rf-filenames-list').find("."+RadicalForm.ErrorFile).remove();
                            if (jQuery.trim(jQuery('.rf-filenames-list').text()) == "") {
                                jQuery('.rf-filenames-list').append("<div>" + RadicalForm.thisFilesWillBeSend + "</div>");
                                jQuery("form").append('<input type="hidden" name="needToSendFiles" value="1" />');
                            }
                            if ("error" in json.responseJSON.data[0]) {
                                jQuery('.rf-filenames-list').append("<div class='"+RadicalForm.ErrorFile+"'>" + json.responseJSON.data[0].error + "</div>"); // add error
                            } else {
                                jQuery('.rf-filenames-list').append("<div>" + json.responseJSON.data[0].name + "</div>"); // add name file
                            }
                        }

                    }
                });
            } else {
                jQuery('.rf-filenames-list').find("."+RadicalForm.ErrorFile).remove();
                jQuery('.rf-filenames-list').append("<div class='"+RadicalForm.ErrorFile+"'>" + RadicalForm.ErrorMax + "</div>"); // size is more than limit
            }

        }
    );


    jQuery("form .rf-button-send").on("click", function (e) {
        var self = jQuery(this).closest('form'),
            needReturn,
            field,
            form = jQuery(this).closest('form').get(0),
            inputArray = jQuery(self).serializeArray();
        if(inputArray.length<6) {
            alert("there is no name attribute for input! Please add name to your input tag!");
            needReturn=true;
        }
        RadicalForm.FormFields=[];
        jQuery(self).find("[name]").removeClass(RadicalForm.DangerClass);

        // let's see the fields of form
        for (var i = 0, len=form.elements.length;i<len; i++) {
            field=form.elements[i];

            if((jQuery(field).hasClass('required') && jQuery.trim(jQuery(field).val())==="") ||
                (jQuery(field).hasClass('required') && (!field.checked) && (field.type==="checkbox")) ||
                (!field.checkValidity()) ) {
                RadicalForm.FormFields.push(field);
                needReturn= true;
            }
        }

        setTimeout(function () {
            for (var i = 0; i < RadicalForm.FormFields.length; i++) {
                jQuery(RadicalForm.FormFields[i]).addClass(RadicalForm.DangerClass);
            }
        },70);

        if (!needReturn) {
            var tmp,
                self2 = this;
            tmp = jQuery(this).html();
            jQuery(this).prop('disabled', true);

            jQuery(this).html(RadicalForm.WaitMessage);

            if(jQuery(self2).data("rfCall")!==undefined) {
                rfCall = String(jQuery(self2).data("rfCall"));
                if(rfCall[0]==="0") {
                    try {
                        rfCall_0(self2);
                    } catch (e) {
                        console.error('Radical Form JS Code: ', e);
                    }
                }
            }
            jQuery.ajax({
                type: "POST",
                url: RadicalForm.Base+"/index.php?option=com_ajax&plugin=radicalform&format=json&group=system",
                dataType: "json",
                data: jQuery(self).serialize(),
                complete: function(data, status) {
	                var message,rfCall;
	                jQuery(self2).html(tmp);
	                jQuery(self2).prop('disabled', false);

	                if(data.responseJSON===undefined) {
                        message=data.responseText;
                    } else {
                        if (data.responseJSON.data[0] === "ok") {
                            message=RadicalForm.AfterSend;
                            jQuery(self2).closest("form").find(".rf-filenames-list").empty();
                            jQuery(self2).closest("form").trigger("reset");
                        } else {
                            message='Error during the sending of form!<br />' + data.responseJSON.data[0];
                        }
                    }

	                if(jQuery(self2).data("rfCall")===undefined) {
	                    rfCall_2(message,self2);
	                } else {
	                    rfCall=String(jQuery(self2).data("rfCall"));
	                    for (var i=0;i<rfCall.length;i++) {
	                        switch (rfCall[i]) {
	                            case "1":
	                                try {
	                                rfCall_1(message,self2);
                                    } catch (e) { console.error('Radical Form JS Code: ', e); }
	                                break;
	                            case "2":
                                    try {
	                                rfCall_2(message,self2);
                                    } catch (e) { console.error('Radical Form JS Code: ', e); }
	                                break;
	                            case "3":
                                    try {
	                                rfCall_3(message,self2);
                                    } catch (e) { console.error('Radical Form JS Code: ', e); }
	                        }

	                    }
	                }
                }
            })

        }
        e.preventDefault();
    });

});