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
    this.danger_classes = RadicalForm.DangerClass.trim().split(/\s+/);
    this.error_file_classes = RadicalForm.ErrorFile.trim().split(/\s+/);

    if (RadicalForm.KeepAlive != 0) {
        window.setInterval(function() {

            var request = new XMLHttpRequest();

            request.open('POST', RadicalForm.Base + '/index.php?option=com_ajax&format=json', true);

            request.onload = function() {

            };

            request.send();

        }, RadicalForm.TokenExpire);
    }

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

        //here we initialize all forms without .rf-form class and those don't have .rf-form inside - we added this class so they have it
        //only forms with this class is used in our work
        var allForms= Array.from(container.querySelectorAll('form:not(.rf-form)')),
            filteredForms;
        filteredForms = allForms.filter(function(el) {
            if(el.querySelector(".rf-form"))
            {
                // we don't add rf-form class to forms with our form inside
                return false;
            }
            return (el.querySelector(".rf-button-send"));
        });

        filteredForms.forEach(function (el){
            el.classList.add('rf-form');
        });

        var request1 = new XMLHttpRequest();
        var AjaxFormDataforToken = new FormData();
        AjaxFormDataforToken.append('gettoken', '1');
        request1.open('POST', RadicalForm.Base + '/index.php?option=com_ajax&plugin=radicalform&format=json&group=system', true);

        request1.onload = function() {
            if (this.status >= 200 && this.status < 400) {
                // Success!
                var data = JSON.parse(this.response);
                [].forEach.call(container.querySelectorAll('.rf-form .rf-button-send'), function (el) {
                    el.insertAdjacentHTML('afterend', '<input type="hidden" name="'+data.data[0]+'" value="1" />');
                });
            }

        };
        request1.send(AjaxFormDataforToken);

        this.on(container, ".rf-form ." + selfClass.danger_classes.join('.'), 'keypress', function (target, e) {
            selfClass.danger_classes.forEach(function (item) {
                target.target.classList.remove(item);
            });
        });

        this.on(container, ".rf-form ." + selfClass.danger_classes.join('.'), 'change', function (target, e) {
            selfClass.danger_classes.forEach(function (item) {
                target.target.classList.remove(item);
            });
        });

        this.on(container, ".rf-form .rf-button-delete", 'click', function (target, e) {
        // click on delete button for uploaded files
            var request = new XMLHttpRequest();

            var filename = selfClass.closest(target.target, "div").querySelector("span").textContent,
                catalog =  selfClass.closest(target.target, "div").dataset.name;

            request.open('POST', RadicalForm.Base + '/index.php?option=com_ajax&plugin=radicalform&format=json&group=system&deletefile=' + filename + '&uniq='+selfClass.uniq + '&catalog='+ catalog, true);

            request.onload = function() {
                if (this.status >= 200 && this.status < 400) {
                    // Success!
                    var data = JSON.parse(this.response);
                    selfClass.closest(target.target, "div").parentNode.removeChild(selfClass.closest(target.target, "div"));
                } else {
                    // We reached our target server, but it returned an error

                }
            };

            request.send();

        });

        var isFramed = false;
        try {
            isFramed = window != window.top || document != top.document || self.location != top.location;
        } catch (e) {
            isFramed = true;
        }
        if (!isFramed) {
            /* page loaded not in the frame - (Yootheme pagebuilder not loaded) */
            if (container.querySelectorAll(".rf-button-send").length !== container.querySelectorAll(".rf-form .rf-button-send").length) {
                alert('ERROR!\r\nThere is form without\r\n the CSS class .rf-form!\r\n Please add CSS class .rf-form to your form. ');
            } else if (container.querySelectorAll(".rf-filenames-list").length !== container.querySelectorAll(".rf-form .rf-filenames-list").length) {
                alert('ERROR!\r\nThere is \r\n.rf-filenames-list\r\n outside of form!\r\n Please move .rf-filenames-list inside the form. ');
            }
        }


        [].forEach.call(container.querySelectorAll('.rf-form .rf-button-send'), function (el) {
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
            form = selfClass.closest(this, '.rf-form');
        if (form === null ) {
            alert("There is no parent with css class .rf-form for your send button!\r\nSee possible explanation in console log.");
            console.log("If you use uikit 3 - it moves the modal window to the end of the document the moment it is opened.\n" +
                "\n" +
                "Thus, the window at the moment of its opening may not be where it was in the original layout.\n" +
                "\n" +
                "Check this with the browser's developer tools at the moment the modal window is open.");
            e.preventDefault();
            return;
        }

        var numberOfInputsWithNames=form.querySelectorAll('input[name], select[name], textarea[name]').length - form.querySelectorAll('input[type="file"]').length;
        if (numberOfInputsWithNames < 2) {
            alert("There is no input tags in your form with 'name' attribute!\r\n Please add 'name' attribute to your input tags!");
            needReturn = true;
        }
        if(form.querySelectorAll("input[name]").length !== form.querySelectorAll('input').length)
        {
            console.log('RadicalForm: there are inputs in your form without name! Please check ');
        }


        RadicalForm.FormFields = [];
        [].forEach.call(form.querySelectorAll("[name]"), function (el) {
           // remove danger classes so they can animated later
            selfClass.danger_classes.forEach(function (item) {
                el.classList.remove(item);
            });
            // let's see the fields of form to check for validity and required
            if ((el.classList.contains('required') && el.value.trim() === "") ||
                (el.classList.contains('required') && (!el.checked) && (el.type === "checkbox")) ||
                (!el.checkValidity())) {
                RadicalForm.FormFields.push(el);
                needReturn = true;
            }
        });


        setTimeout(function () {
            for (var i = 0; i < RadicalForm.FormFields.length; i++) {
                selfClass.danger_classes.forEach(function (item) {
                    RadicalForm.FormFields[i].classList.add(item);
                })
            }
        }, 70);

        if (this.dataset.rfCall !== undefined) {
            var rfCall = String(this.dataset.rfCall);
            if (rfCall[0] === "0") {
                if (typeof (rfCall_0) === "function") {
                    try {
                        var returnOfpreCall = rfCall_0(this, needReturn);
                        if ((returnOfpreCall !== undefined) && (returnOfpreCall === false)) {
                            needReturn = true;
                        }
                    } catch (e) {
                        console.error('Radical Form JS Code: ', e);
                    }
                }
                else
                {
                    console.error("Function rfCall_0 doesn't set at RadicalForm plugin settings");
                }
            }
        }

        if (!needReturn) {

            var prevousButtonText = this.innerHTML,
                buttonPressed = this;

            this.disabled = true;
            this.innerHTML = RadicalForm.WaitMessage;


            var AjaxFormData = new FormData(); //form data without the file inputs
            AjaxFormData.append('rfUserAgent', window.navigator.userAgent);
            if(form.getAttribute('id') !== null) {
                AjaxFormData.append('rfFormID', form.getAttribute('id'));
            }
            AjaxFormData.append('uniq', selfClass.uniq);
            AjaxFormData.append('url', window.location.href);
            AjaxFormData.append('rf-time', selfClass.showTime());
            AjaxFormData.append('rf-duration', (performance.now()/1000).toFixed(2));
            AjaxFormData.append('reffer', document.referrer);
            AjaxFormData.append('resolution', screen.width + 'x' + screen.height);
            AjaxFormData.append('pagetitle', document.title.replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;"));


            var elements = form.querySelectorAll('input[name], select[name], textarea[name]');

            var option, optValue;
            RadicalForm.Contacts = {};

            for (var i = 0; i < elements.length; i++) {
                field = elements[i];
                switch (field.type){
                    case "select-one":
                    case "select-multiple":

                        if (field.name.length) {
                            for (var j = 0, optLen = field.options.length; j < optLen; j++) {
                                option = field.options[j];
                                if (option.selected) {
                                    optValue = "";
                                    if (option.hasAttribute) {
                                        optValue = (option.hasAttribute("value") ? option.value : option.text);
                                    } else {
                                        optValue = ( option.attributes["value"].specified ? option.value: option.text);
                                    }
                                    AjaxFormData.append(field.name, optValue);
                                }
                            }

                        }
                        break;
                    case undefined:
                    case "file":
                    case "submit":
                    case "reset":
                    case "button":
                        break;
                    case "radio":
                    case "checkbox":
                        if(!field.checked) {
                            break;
                        }
                    default:
                        if (field.name.length) {
                            AjaxFormData.append(field.name, field.value);
                            if (field.name === "phone") {
                                RadicalForm.Contacts.phone = field.value;
                            }
                            if (field.name === "name") {
                                RadicalForm.Contacts.name = field.value;
                            }
                            if (field.name === "email") {
                                RadicalForm.Contacts.email = field.value;
                            }
                        }

                }
            }


            if (RadicalForm.Jivosite === "1") {

                try {
                    if (Object.keys(RadicalForm.Contacts).length !== 0) {
                        jivo_api.setContactInfo(RadicalForm.Contacts);
                    }
                } catch (e) {
                    console.error('Radical Form JS Code: ', e);
                }

            }

            if (RadicalForm.Verbox === "1") {

                try {
                    if (Object.keys(RadicalForm.Contacts).length !== 0) {
                        Verbox("setClientInfo", RadicalForm.Contacts);
                    }
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
                    selfClass.clearForm(form);

                    var response = false;
                    try {
                        response = JSON.parse(this.response);
                    } catch (e) {
                        response = false;
                        try {
                            rfCall_9(('Response code: ' + request.status + '\n' + e.message + '\n' + this.response), buttonPressed);
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

                                    if (result.result === "fail") {
                                        var a = {};
                                        try {
                                            jivo_api.sendMessage(a, response.data[0][1]);
                                        } catch (e) {
                                            console.error('Radical Form JS Code: ', e);
                                        }
                                    }

                                } catch (e) {
                                    console.error('Radical Form JS Code: ', e);
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
                            message = 'Error! ' + response.data[0];
                            try {
                                rfCall_9(message, buttonPressed);
                            } catch (e) {
                                console.error('Radical Form JS Code: ', e);
                            }
                        }

                    } else {
                        try {
                            rfCall_9((response.message),buttonPressed);
                        } catch (e) {
                            console.error('Radical Form JS Code: ', e);
                        }
                    }
                } else if (this.readyState === 4 && this.status !== 200) {
                    buttonPressed.innerHTML=prevousButtonText;
                    buttonPressed.disabled=false;
                    try {
                        rfCall_9((request.status + ' ' + request.message), buttonPressed);
                    } catch (e) {
                        console.error('Radical Form JS Code: ', e);
                    }
                }
            };
        }
        e.preventDefault();
    };

    /**
     * Send file to server
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
            form = selfClass.closest(this, '.rf-form'),
            rf_filenames_list = form.querySelector('.rf-filenames-list') || document.createElement('div'),
            buttonPressed = this,
            rfDelete="&nbsp;<svg class=\"rf-button-delete\" style=\"cursor: pointer;\" height=\"16\" viewBox=\"0 0 512 512\" width=\"16\" xmlns=\"http://www.w3.org/2000/svg\"><path d=\"M256 0C114.836 0 0 114.836 0 256s114.836 256 256 256 256-114.836 256-256S397.164 0 256 0zm0 0\" fill=\"" + RadicalForm.DeleteBackground + "\"/><path d=\"M350.273 320.105c8.34 8.344 8.34 21.825 0 30.168a21.275 21.275 0 01-15.086 6.25c-5.46 0-10.921-2.09-15.082-6.25L256 286.164l-64.105 64.11a21.273 21.273 0 01-15.083 6.25 21.275 21.275 0 01-15.085-6.25c-8.34-8.344-8.34-21.825 0-30.169L225.836 256l-64.11-64.105c-8.34-8.344-8.34-21.825 0-30.168 8.344-8.34 21.825-8.34 30.169 0L256 225.836l64.105-64.11c8.344-8.34 21.825-8.34 30.168 0 8.34 8.344 8.34 21.825 0 30.169L286.164 256zm0 0\" fill=\""  +RadicalForm.DeleteColor + "\"/></svg>";

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
                        rf_filenames_list.insertAdjacentHTML('beforeend', "<div class='" + selfClass.error_file_classes.join(' ') + "'>Unknown Error. See Console.</div>");
                        response = false;
                        return;
                    }
                    if (response.success) {
                        var el=rf_filenames_list.querySelector("." + selfClass.error_file_classes.join('.'));

                        if (el) {
                            el.parentNode.removeChild(el);
                        }
                        if ("error" in response.data[0]) {
                            rf_filenames_list.insertAdjacentHTML('beforeend', "<div class='" + selfClass.error_file_classes.join(' ') + "'>" + response.data[0].error + "</div>");
                        } else {
                            if (rf_filenames_list.textContent.trim() === "") {
                                rf_filenames_list.insertAdjacentHTML('beforeend', "<div>" + RadicalForm.thisFilesWillBeSend + "</div>");
                            }
                            if(!form.querySelector("input[name=needToSendFiles]")) {
                                form.insertAdjacentHTML('beforeend', '<input type="hidden" name="needToSendFiles" value="1" />');
                            }
                            rf_filenames_list.insertAdjacentHTML('beforeend', "<div data-name='" + response.data[0].key + "'><span>" + response.data[0].name + "</span>" + rfDelete + "</div>");
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
                        rfCall_9((request.status + ' ' + request.message), buttonPressed);
                    } catch (e) {
                        console.error('Radical Form JS Code: ', e);
                    }
                    console.error(request.status + ' ' + request.message);
                }
            };

        } else {
            var el=rf_filenames_list.querySelector("." + selfClass.error_file_classes.join('.'));

            if (el) {
                el.parentNode.removeChild(el);
            }

            rf_filenames_list.insertAdjacentHTML('beforeend',"<div class='" + selfClass.error_file_classes.join(' ') + "'>" + RadicalForm.ErrorMax + "</div>"); // size is more than limit
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

    // clear the form after sending
    this.clearForm = function (formToClear) {

        var elements = Array.from(formToClear.querySelectorAll('input[name], select[name], textarea[name]'));


        for(var i=0; i<elements.length; i++) {

            switch(elements[i].type.toLowerCase()) {

                case "radio":
                case "checkbox":
                    if (elements[i].checked) {
                        elements[i].checked = false;
                    }
                    break;

                case "select-one":
                case "select-multiple":
                    elements[i].selectedIndex = -1;
                    break;

                case undefined:
                case "file":
                case "submit":
                case "reset":
                case "button":
                case "hidden":
                    break;

                default:
                    elements[i].value = "";
                    break;
            }
        }
    }
    this.showTime = function () {
        var monthsArr = ["01", "02", "03", "04", "05", "06",
            "07", "08", "09", "10", "11", "12"];
        var dateObj = new Date();
        var year = dateObj.getFullYear();
        var month = dateObj.getMonth();
        var numDay = dateObj.getDate();
        var hour = dateObj.getHours();
        var minute = dateObj.getMinutes();
        var second = dateObj.getSeconds();

        if (minute < 10) minute = "0" + minute;

        if (second < 10) second = "0" + second;

        return hour + ":" + minute + ":" + second + ", " + numDay + "." + monthsArr[month]
            + "." + year;
    }
    // This function is for RadicalForm Elements Steps
    this.nextStep = function (el,targetStep,animationStep,previous) {

        // targetStep - это путь от родительского класса к обрамляющему фрейму шага, которое гасится атрибутом hidden при переключении шагов
        // el - это текущая кнопка "далее"

        var step = selfClass.closest(el,targetStep); // это путь текущему фрейму шага, который все обрамляет
        var needReturn = false; // признак нарушения заполненности полей
        var elementsArray = document.querySelectorAll(targetStep + " .rf-next"); // список всех кнопок квиза
        var currentButtonNext = step.querySelector(".rf-next"); // ищем текущую кнопку Next, так как при нажатии на Previous мы должны работать с кнопками Next
        var currentIndex = Array.from(elementsArray).indexOf(currentButtonNext);
        // Если элемент не найден или является последним в массиве и при этому не является кнопкой назад
        if(!previous) {
            if (currentIndex === -1 || currentIndex === elementsArray.length - 1 ) {
                console.log("Last element reached ");
                return null; // то ничего не делаем и возвращаемся
            }
            var nextEl = elementsArray[currentIndex + 1]; // следующая кнопка далее
            var nextStep = selfClass.closest(nextEl, targetStep);
        }

        var previousEl = null;
        var previousStep = null;
        if (currentIndex > 0) {
            previousEl = elementsArray[currentIndex - 1]; // предыдущая кнопка далее
            previousStep = selfClass.closest(previousEl, targetStep);
        }

        if(previous) {
            // we go to the previous step, so we need to remove all events from button next of the previous step
            var elementForClone = previousEl,
                elementCloned = elementForClone.cloneNode(true);

            elementForClone.parentNode.replaceChild(elementCloned, elementForClone);
            nextStep = previousStep;
        } else {
            RadicalForm.FormFields = [];
            [].forEach.call(step.querySelectorAll("[name]"), function (el) {
                // remove danger classes so they can be animated later
                selfClass.danger_classes.forEach(function (item) {
                    el.classList.remove(item);
                });
                // let's see the fields of form to check for validity and required
                if ((el.classList.contains('required') && el.value.trim() === "") ||
                    (el.classList.contains('required') && (!el.checked) && (el.type === "checkbox")) ||
                    (!el.checkValidity())) {
                    RadicalForm.FormFields.push(el);
                    needReturn = true;
                }
            });

            setTimeout(function () {
                for (var i = 0; i < RadicalForm.FormFields.length; i++) {
                    selfClass.danger_classes.forEach(function (item) {
                        RadicalForm.FormFields[i].classList.add(item);
                    })
                }
            }, 70);
        }

        if (!needReturn) {
            if(animationStep) {
                UIkit.toggle(el,{
                    target: [step, nextStep],
                    animation: animationStep
                }).toggle();
            } else {
                UIkit.toggle(el,{
                    target: [step, nextStep]
                }).toggle();
            }
        }

    }
};

ready(function () {
    RadicalForm.RadicalFormClass = new RadicalFormClass;
    RadicalForm.RadicalFormClass.init();
});
