
jQuery(document).ready(function () {
    jQuery(function ($) {

        function getUrlParams(url){
            var regex = /[?&]([^=#]+)=([^&#]*)/g,
                params = {},
                match;
            while(match = regex.exec(url)) {
                params[match[1]] = match[2];
            }
            return params;
        }

        var currentGetParams,
            page;
        currentGetParams = getUrlParams(location.search);

        var historyClear = document.querySelector("#historyclear");
        if(historyClear)
        {
            historyClear.addEventListener('click', function (event) {
                historyClear.innerHTML  = "Wait...";
                historyClear.disabled = true;
                if ('page' in currentGetParams )
                {
                    page=currentGetParams.page;
                }
                else
                {
                    page="0";
                }

                $.getJSON("index.php?option=com_ajax&plugin=radicalform&format=json&group=system&admin=2&page="+page, function (data) {
                    location.reload();
                });

                event.preventDefault();
            });
        }

        var numberClear = document.querySelector("#numberclear");
        if(numberClear)
        {
            numberClear.addEventListener('click', function (event) {
                numberClear.innerHTML  = "Wait...";
                numberClear.disabled = true;
                $.getJSON("index.php?option=com_ajax&plugin=radicalform&format=json&group=system&admin=3", function (data) {
                    location.reload();
                });
                event.preventDefault();
            });
        }

        var exportCSV = document.querySelector("#exportcsv");
        if(exportCSV)
        {
            exportCSV.addEventListener('click', function (event) {
                var temp=exportCSV.innerHTML;
                exportCSV.innerHTML = "Wait...";
                exportCSV.disabled = true;

                setTimeout(function () {
                    exportCSV.innerHTML = temp;
                    exportCSV.disabled = false;
                }, 3000)
            });
        }




//show the info about need to save parameters
        [].forEach.call(document.querySelectorAll('#attrib-list label.btn'), function (el) {
            el.addEventListener('click',function (e) {
                if(!document.querySelector("#attrib-list .alert.alert-info.hidden")) return;
                document.querySelector("#attrib-list .alert.alert-info.hidden").classList.remove("hidden");
            });
        });


        document.querySelector("#radicalformcheck").addEventListener('click', function (event) {
        var radicalformcheck=document.querySelector("#radicalformcheck"),
            temp = radicalformcheck.innerHTML;
            radicalformcheck.innerHTML="Wait...";
            radicalformcheck.disabled = true;


            var request = new XMLHttpRequest();
            request.open('GET', 'index.php?option=com_ajax&plugin=radicalform&format=json&group=system&admin=1', true);

            request.onload = function() {
                if (this.status >= 200 && this.status < 400) {
                    // Success!
                    var data = JSON.parse(this.response);
                    if(data.data[0].ok) {
                        var output=data.data[0].chatids;
                        if(output.length>0) {

                            for(var i=0;i<output.length;i++) {
                                var found=false;
                                [].forEach.call(document.querySelectorAll('#attrib-advanced .adminlist.table tr td:first-child input'), function (el) {
                                    if(el.value==output[i].chatID) {
                                        found=true;
                                    }
                                })

                                if(!found) {
                                    var event = document.createEvent('HTMLEvents');
                                    event.initEvent('click', true, false);
                                    document.querySelector("#attrib-advanced th a").dispatchEvent(event);
                                    console.log(document.querySelector("#attrib-advanced th a"));
                                    var lastString=document.querySelectorAll("#attrib-advanced .adminlist.table tr:last-child input");
                                    lastString[0].value=output[i].chatID;
                                    lastString[1].value=output[i].name;

                                }
                            }
                        } else {
                            document.querySelector("#radicalformcheck").insertAdjacentHTML("afterend","<div class=\"alert alert-note input-xxlarge telegram-note\"><button type=\"button\" class=\"close\" data-dismiss=\"alert\">×</button>There are no messages to bot</div>")
                        }


                    } else {
                        document.querySelector("#radicalformcheck").insertAdjacentHTML("afterend","<div class=\"alert alert-error input-xxlarge telegram-note\"><button type=\"button\" class=\"close\" data-dismiss=\"alert\">×</button><h4>Код ошибки "+data.data[0].error_code+"</h4><span>"+data.data[0].description+"</span></div>")

                    }

                } else {
                    // We reached our target server, but it returned an error
                    document.querySelector("#radicalformcheck").insertAdjacentHTML("afterend","<div class=\"alert alert-error input-xxlarge telegram-note\"><button type=\"button\" class=\"close\" data-dismiss=\"alert\">×</button><h4>Error </h4><span>"+this.response+"</span></div>")

                }
                radicalformcheck.disabled = false;
                radicalformcheck.innerHTML = temp;
            };

            request.onerror = function() {
                // There was a connection error of some sort
                document.querySelector("#radicalformcheck").insertAdjacentHTML("afterend","<div class=\"alert alert-error input-xxlarge telegram-note\"><button type=\"button\" class=\"close\" data-dismiss=\"alert\">×</button><h4>Error </h4><span>Error connection</span></div>")

                radicalformcheck.disabled = false;
                radicalformcheck.innerHTML = temp;
            };

            request.send();

            event.preventDefault();
        });


    });
});

