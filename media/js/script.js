if(!window.jQuery) {console.log("RadicalForm: There is no jQuery library!")}
jQuery(document).ready(function () {

    var uniq = (new Date).getTime() + Math.floor(Math.random()*100); // создаем уникальный id для загрузки файлов

    jQuery(".rf-button-send").after('<input type="hidden" name="uniq" value="'+uniq+'" />')
                             .after(rfToken)
                            .after('<input type="hidden" name="url" value="'+window.location.href+'" />')
                            .after('<input type="hidden" name="resolution" value="'+screen.width +'x' + screen.height+'" />')
                            .after('<input type="hidden" name="reffer" value="'+document.referrer+'" />');

    var temp=RadicalForm.DangerClass.split(" ");
    RadicalForm.DangerClasses=temp.join(".");
    jQuery("body").on("keypress","form input.required."+RadicalForm.DangerClasses, function (e) {
        jQuery(this).removeClass(RadicalForm.DangerClass);
    })
        .on("keypress","form textarea.required."+RadicalForm.DangerClasses, function (e) {
        jQuery(this).removeClass(RadicalForm.DangerClass);
    })
        .on("change","form select.required."+RadicalForm.DangerClasses, function (e) {
        jQuery(this).removeClass(RadicalForm.DangerClass);
    });


    // по нажатию "загрузить файл"
    jQuery("input[type='file'].rf-upload-button").on("change", function (e) {
            if(!jQuery(this).attr("name")) {console.log("RadicalForm: There is no 'name' attribute for rf-upload-button!"); return; }

            var that = jQuery(this).closest('form'),
                textForUploadButton = jQuery(this).siblings('.rf-upload-button-text')[0],
                tmp = jQuery(textForUploadButton).html(),
                formData = new FormData(); // передаем новому элементу нашу форму
            formData.append(this.name, this.files[0]);
            if(this.files[0].size<RadicalForm.MaxSize) {
                formData.append("uniq", uniq);

                jQuery(textForUploadButton).html(RadicalForm.waitingForUpload)
                    .prop('disabled', true);

                jQuery.ajax({
                    url: '/index.php?option=com_ajax&plugin=radicalform&format=json&group=system&file=1&size=' + this.files[0].size,
                    type: 'post',
                    contentType: false, // важно - убираем форматирование данных по умолчанию
                    processData: false, // важно - убираем преобразование строк по умолчанию
                    dataType: "json",
                    data: formData,
                    complete: function (json, status) {

                        jQuery(textForUploadButton).html(tmp)
                            .prop('disabled', false);
                        if ("error" in json.responseJSON) {
                            jQuery('.rf-filenames-list').append("<div>" + json.responseJSON.error + "</div>"); // добавляем имя файла
                        } else {
                            jQuery('.rf-filenames-list').find("."+RadicalForm.ErrorFile).remove();
                            if (jQuery.trim(jQuery('.rf-filenames-list').text()) == "") {
                                jQuery('.rf-filenames-list').append("<div>" + RadicalForm.thisFilesWillBeSend + "</div>");
                                jQuery("form").append('<input type="hidden" name="needToSendFiles" value="1" />');
                            }
                            if ("error" in json.responseJSON.data[0]) {
                                jQuery('.rf-filenames-list').append("<div class='"+RadicalForm.ErrorFile+"'>" + json.responseJSON.data[0].error + "</div>"); // добавляем ошибку
                            } else {
                                jQuery('.rf-filenames-list').append("<div>" + json.responseJSON.data[0].name + "</div>"); // добавляем имя файла
                            }
                        }

                    }
                });
            } else {
                jQuery('.rf-filenames-list').find("."+RadicalForm.ErrorFile).remove();
                jQuery('.rf-filenames-list').append("<div class='"+RadicalForm.ErrorFile+"'>" + RadicalForm.ErrorMax + "</div>"); // добавляем ошибку о превышении размера
            }

        }
    );


    jQuery("form .rf-button-send").on("click", function (e) {
        var self = jQuery(this).closest('form'),
            needReturn,
            inputArray = jQuery(self).serializeArray();
        if(inputArray.length<6) {
            alert("there is no name attributes in your form!");
            needReturn=true;
        }
        RadicalForm.FormFields=[];
        jQuery(self).find("[name]").removeClass(RadicalForm.DangerClass);
        // просмотрим все переданные поля в форме
        for (var i = 0; i < inputArray.length; i++) {

            // если поле пустое то помечаем его красным
            if (jQuery(self).find("[name='" + inputArray[i].name + "']").hasClass('required') && jQuery.trim(inputArray[i].value) == "") {
                RadicalForm.FormFields[i]=jQuery(self).find("[name='" + inputArray[i].name + "']").get(0);
                needReturn = true; // признак того что надо прервать отсылку и выйти. была обнаружена ошибка в валидации полей
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
                    rfCall_0(self2);
                }
            }
            jQuery.ajax({
                type: "POST",
                url: "/index.php?option=com_ajax&plugin=radicalform&format=json&group=system",
                dataType: "json",
                data: jQuery(self).serialize(),
                complete: function(data, status) {
	                var message,rfCall;
	                jQuery(self2).html(tmp);
	                jQuery(self2).prop('disabled', false);

	                if (data.responseJSON.data[0] === "ok") {
	                    message=RadicalForm.AfterSend;
	                    jQuery(self2).closest("form").find(".rf-filenames-list").empty();
	                    jQuery(self2).closest("form").trigger("reset");
	                } else {
	                    message='Error during the sending of form!<br />' + data.responseJSON.data[0];
	                }
	                if(jQuery(self2).data("rfCall")===undefined) {
	                    rfCall_2(message);
	                } else {
	                    rfCall=String(jQuery(self2).data("rfCall"));
	                    for (var i=0;i<rfCall.length;i++) {
	                        switch (rfCall[i]) {
	                            case "1":
	                                rfCall_1(message,self2);
	                                break;
	                            case "2":
	                                rfCall_2(message,self2);
	                                break;
	                            case "3":
	                                rfCall_3(message,self2);
	                        }

	                    }
	                }
                }
            })

        }
        e.preventDefault();
    });

});