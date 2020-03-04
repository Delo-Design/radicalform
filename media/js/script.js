function ready(fn) {
    if (document.readyState != 'loading'){
        fn();
    } else {
        document.addEventListener('DOMContentLoaded', fn);
    }
}

RadicalFormClass = function () {

    var selfClass = this;

    /**
     * get uniq id for upload a file.
     * @type {number}
     */
    this.uniq = (new Date).getTime() + Math.floor(Math.random() * 100);

    /**
     *
     * @type {array}
     */
    this.danger_сlasses = RadicalForm.DangerClass.split(" ");


    /**
     * Init for DOM element
     * @param container
     */
    this.init = function(container) {
       
        if(typeof container === 'string') {
            container = document.querySelector(container);
        } else {
            if(container === null || container === undefined) {
                container = document.querySelector('body')
            }
        }

        [].forEach.call(container.querySelectorAll('.rf-button-send'), function (el) {
            el.insertAdjacentHTML('afterend', '<input type="hidden" name="uniq" value="' + selfClass.uniq + '" />');
            el.insertAdjacentHTML('afterend', RadicalForm.Token);
            el.insertAdjacentHTML('afterend', '<input type="hidden" name="url" value="' + window.location.href + '" />');
            el.insertAdjacentHTML('afterend', '<input type="hidden" name="resolution" value="' + screen.width + 'x' + screen.height + '" />');
            el.insertAdjacentHTML('afterend', '<input type="hidden" name="pagetitle" value="' + document.title.replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;") + '" />');
            el.insertAdjacentHTML('afterend', '<input type="hidden" name="reffer" value="' + document.referrer + '" />');
        });

        this.on(container, "form ." + selfClass.danger_сlasses.join('.'), 'keypress', function (target, e) {
            selfClass.danger_сlasses.forEach(function (item) {
                target.target.classList.remove(item);
            });
        });

        this.on(container, "form ." + selfClass.danger_сlasses.join('.'), 'change', function (target, e) {
            selfClass.danger_сlasses.forEach(function (item) {
                target.target.classList.remove(item);
            });
        });

        if (container.querySelectorAll(".rf-filenames-list").length !== container.querySelectorAll("form .rf-filenames-list").length) {
            alert('ERROR!\r\nThere is \r\n.rf-filenames-list\r\n outside of form!\r\n Please move .rf-filenames-list inside the form. ');
        }

        [].forEach.call(container.querySelectorAll('form .rf-button-send'), function (el) {
            el.addEventListener('click', selfClass.formSend);
        });

        [].forEach.call(container.querySelectorAll("input[type='file'].rf-upload-button"), function (el) {
            el.addEventListener('change', selfClass.fileSend);
        });

    };

    /**
     * Send form
     * @param e
     */
    this.formSend = function(e) {
        var needReturn = false,
            field,
            form = selfClass.closest(this, 'form');

        var numberOfInputsWithNames=form.querySelectorAll("input[name]").length - form.querySelectorAll('input[type="file"]').length;
        if (numberOfInputsWithNames < 7) {
            alert("There is no input tags in your form with 'name' attribute!\r\n Please add 'name' attribute to your input tags!");
            needReturn = true;
        }
        RadicalForm.FormFields = [];
        [].forEach.call(form.querySelectorAll("[name]"), function (el) {
            selfClass.danger_сlasses.forEach(function (item) {
                el.classList.remove(item);
            });
        });

        // let's see the fields of form to check for validity and required
        for (var i = 0, len = form.elements.length; i < len; i++) {
            field = form.elements[i];
            if ((field.classList.contains('required') && field.value.trim() === "") ||
                (field.classList.contains('required') && (!field.checked) && (field.type === "checkbox")) ||
                (!field.checkValidity())) {
                RadicalForm.FormFields.push(field);
                needReturn = true;
            }
        }

        setTimeout(function () {
            for (var i = 0; i < RadicalForm.FormFields.length; i++) {
                selfClass.danger_сlasses.forEach(function (item) {
                    RadicalForm.FormFields[i].classList.add(item);
                })
            }
        }, 70);

        if (this.dataset.rfCall !== undefined) {
            var rfCall = String(this.dataset.rfCall);
            if (rfCall[0] === "0") {
                try {
                   var returnOfpreCall = rfCall_0(this, needReturn);
                   if ( (returnOfpreCall !== undefined) && (returnOfpreCall === false) ) {
                       needReturn = true;
                   }
                } catch (e) {
                    console.error('Radical Form JS Code: ', e);
                }
            }
        }

        if (!needReturn) {

            var prevousButtonText = this.innerHTML,
                buttonPressed = this;

            this.disabled = true;
            this.innerHTML = RadicalForm.WaitMessage;


            // we need only input tags that not file input tags. So we remove name from input of type file.
            [].forEach.call(form.querySelectorAll('input[type="file"]'), function (el) {
                if(el.getAttribute('name')) {
                    el.dataset.name=el.getAttribute('name');
                }
                el.removeAttribute("name");
            });

            var AjaxFormData = new FormData(form); //form data without the file inputs
            AjaxFormData.append('rfUserAgent', window.navigator.userAgent);
            if(form.getAttribute('id') !== null) {
                AjaxFormData.append('rfFormID', form.getAttribute('id'));
            }

            // here we return the previous state of the inputs of file type
            [].forEach.call(form.querySelectorAll('input[type="file"]'), function (el) {
                if(el.dataset.name) {
                    el.setAttribute('name', el.dataset.name);
                }
                el.removeAttribute("data-name");
            });

            if (RadicalForm.Jivosite === "1") {
                RadicalForm.Contacts = {};

                for (i = 0; i < form.elements.length; ++i) {
                    if (form.elements[i].name === "phone") {
                        RadicalForm.Contacts.phone = form.elements[i].value;
                    }
                    if (form.elements.name === "name") {
                        RadicalForm.Contacts.name = form.elements[i].value;
                    }
                    if (form.elements.name === "email") {
                        RadicalForm.Contacts.email = form.elements[i].value;
                    }

                }
                try {
                    jivo_api.setContactInfo(RadicalForm.Contacts);
                } catch (e) {
                    console.error('Radical Form JS Code: ', e);
                }

            }

            if (RadicalForm.Verbox === "1") {
                RadicalForm.Contacts = {};

                for (i = 0; i < form.elements.length; ++i) {
                    if (form.elements[i].name === "phone") {
                        RadicalForm.Contacts.phone = form.elements[i].value;
                    }
                    if (form.elements.name === "name") {
                        RadicalForm.Contacts.name = form.elements[i].value;
                    }
                    if (form.elements.name === "email") {
                        RadicalForm.Contacts.email = form.elements[i].value;
                    }
                    if (form.elements.name === "rfSubject") {
                        RadicalForm.Contacts.questionCategory = form.elements[i].value;
                    }

                }
                try {
                    Verbox("setClientInfo",RadicalForm.Contacts);
                } catch (e) {
                    console.error('Radical Form JS Code: ', e);
                }

            }

            var  request = new XMLHttpRequest(),
                requestUrl = RadicalForm.Base + "/index.php?option=com_ajax&plugin=radicalform&group=system&format=json";
            request.open('POST', requestUrl);
            request.send(AjaxFormData);
            request.onreadystatechange = function () {
                var rfCall, message;
                if (this.readyState === 4 && this.status === 200) {
                    buttonPressed.innerHTML=prevousButtonText;
                    buttonPressed.disabled=false;
                    if (form.querySelector(".rf-filenames-list")) {
                        form.querySelector(".rf-filenames-list").innerHTML="";
                    }

                    //clear all fields of the form
                    form.reset();

                    var response = false;
                    try {
                        response = JSON.parse(this.response);
                    } catch (e) {
                        response = false;
                        try {
                            rfCall_2(('Response code: ' + request.status + '\n' + e.message + '\n' + this.response), buttonPressed);
                        } catch (e) {
                            console.error('Radical Form JS Code: ', e);
                        }
                        return;
                    }
                    if (response.success) {
                        if (response.data[0][0]==="ok") {
                            if (RadicalForm.Jivosite === "1") {
                                try {
                                    var result = jivo_api.sendOfflineMessage({
                                        "message": response.data[0][1]
                                    });
                                } catch (e) {
                                    console.error('Radical Form JS Code: ', e);
                                }
                                if (result.result === "fail") {
                                    var a = {};
                                    try {
                                        jivo_api.sendMessage(a, response.data[0][1]);
                                    } catch (e) {
                                        console.error('Radical Form JS Code: ', e);
                                    }
                                }
                            }
                            if (RadicalForm.Verbox === "1") {
                                try {
                                    Verbox("sendMessage", response.data[0][1]);
                                } catch (e) {
                                    console.error('Radical Form JS Code: ', e);
                                }
                             }

                            message = RadicalForm.AfterSend;
                        } else {
                            message = 'Error! ' + response.data[0];
                        }


                        if (buttonPressed.dataset.rfCall === undefined) {
                            try {
                                rfCall_2(message, buttonPressed);
                            } catch (e) {
                                console.error('Radical Form JS Code: ', e);
                            }
                        } else {
                            rfCall = String(buttonPressed.dataset.rfCall);
                            for (var i = 0; i < rfCall.length; i++) {
                                switch (rfCall[i]) {
                                    case "1":
                                        try {
                                            rfCall_1(message, buttonPressed);
                                        } catch (e) {
                                            console.error('Radical Form JS Code: ', e);
                                        }
                                        break;
                                    case "2":
                                        try {
                                            rfCall_2(message, buttonPressed);
                                        } catch (e) {
                                            console.error('Radical Form JS Code: ', e);
                                        }
                                        break;
                                    case "3":
                                        try {
                                            rfCall_3(message, buttonPressed);
                                        } catch (e) {
                                            console.error('Radical Form JS Code: ', e);
                                        }
                                }

                            }
                        }
                    } else {
                        try {
                            rfCall_2((response.message),buttonPressed);
                        } catch (e) {
                            console.error('Radical Form JS Code: ', e);
                        }
                    }
                } else if (this.readyState === 4 && this.status !== 200) {
                    buttonPressed.innerHTML=prevousButtonText;
                    buttonPressed.disabled=false;
                    try {
                        rfCall_2((request.status + ' ' + request.message), buttonPressed);
                    } catch (e) {
                        console.error('Radical Form JS Code: ', e);
                    }
                }
            };
        }
        e.preventDefault();
    };

    /**
     * Send file to server for save
     * @param e
     */
    this.fileSend = function(e) {
        if (!this.getAttribute("name")) {
            alert("RadicalForm: There is no 'name' attribute for rf-upload-button!\r\nFile can't uploaded. Please, add name attribute for file input tag.");
            return;
        }

        var textForUploadButton = this.parentNode.querySelector('.rf-upload-button-text'),
            previousTextForUploadButton = textForUploadButton ? textForUploadButton.innerHTML : "",
            formData = new FormData(),
            form = selfClass.closest(this, 'form'),
            rf_filenames_list = form.querySelector('.rf-filenames-list') || document.createElement('div');

        formData.append(this.name, this.files[0]);

        if (this.files[0].size < RadicalForm.MaxSize) {
            formData.append("uniq", selfClass.uniq);

            if(textForUploadButton) {
                textForUploadButton.innerHTML = RadicalForm.waitingForUpload;
                textForUploadButton.disabled = true;
            }

            var request = new XMLHttpRequest(),
                requestUrl = RadicalForm.Base + '/index.php?option=com_ajax&plugin=radicalform&format=json&group=system&file=1&size=' + this.files[0].size;

            request.open('POST', requestUrl);
            request.send(formData);
            request.onreadystatechange = function () {
                if (this.readyState === 4 && this.status === 200) {
                    if(textForUploadButton) {
                        textForUploadButton.disabled = false;
                        textForUploadButton.innerHTML = previousTextForUploadButton;
                    }
                    var response = false;
                    try {
                        response = JSON.parse(this.response);
                    } catch (e) {
                        console.error(request.status + ' ' + e.message + ' ' + this.response);
                        response = false;
                        return;
                    }
                    if (response.success) {
                        var el=rf_filenames_list.querySelector("." + RadicalForm.ErrorFile);

                        if (el) {
                            el.parentNode.removeChild(el);
                        }
                        if ("error" in response.data[0]) {
                            rf_filenames_list.insertAdjacentHTML('beforeend', "<div class='" + RadicalForm.ErrorFile + "'>" + response.data[0].error + "</div>");
                        } else {
                            if (rf_filenames_list.textContent.trim() === "") {
                                rf_filenames_list.insertAdjacentHTML('beforeend', "<div>" + RadicalForm.thisFilesWillBeSend + "</div>");
                                form.insertAdjacentHTML('beforeend', '<input type="hidden" name="needToSendFiles" value="1" />');
                            }
                            rf_filenames_list.insertAdjacentHTML('beforeend', "<div>" + response.data[0].name + "</div>");
                        }

                    } else {
                        rf_filenames_list.insertAdjacentHTML('beforeend', "<div>" + response.message + "</div>");
                    }
                } else if (this.readyState === 4 && this.status !== 200) {
                    if(textForUploadButton) {
                        textForUploadButton.disabled = false;
                        textForUploadButton.innerHTML = previousTextForUploadButton;
                    }

                    try {
                        rfCall_2((request.status + ' ' + request.message), buttonPressed);
                    } catch (e) {
                        console.error('Radical Form JS Code: ', e);
                    }
                    console.error(request.status + ' ' + request.message);
                }
            };

        } else {
            var el=rf_filenames_list.querySelector("." + RadicalForm.ErrorFile);

            if (el) {
                el.parentNode.removeChild(el);
            }

            rf_filenames_list.insertAdjacentHTML('beforeend',"<div class='" + RadicalForm.ErrorFile + "'>" + RadicalForm.ErrorMax + "</div>"); // size is more than limit
        }

    };

    this.on = function (el, selector, event, cb) {
        el.addEventListener(event, function (e) {
            for (var target = e.target; target && target != this; target = target.parentNode) {
                var matchesSelector = target.matches || target.webkitMatchesSelector || target.mozMatchesSelector || target.msMatchesSelector;
                if (matchesSelector.call(target, selector)) {
                    cb.call(target, e);
                    break;
                }
            }
        }, false);
    };

    this.closest =  function(el, selector) {
        var matchesSelector = el.matches || el.webkitMatchesSelector || el.mozMatchesSelector || el.msMatchesSelector;

        while (el) {
            if (matchesSelector.call(el, selector)) {
                return el;
            } else {
                el = el.parentElement;
            }
        }
        return null;
    }

};

ready(function () {
    RadicalForm.RadicalFormClass = new RadicalFormClass;
    RadicalForm.RadicalFormClass.init();
});
