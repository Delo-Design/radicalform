jQuery(document).ready(function () {
    jQuery(function ($) {

        $("#general .adminlist.table").css("max-width",760);
        $("#general .adminlist.table th:nth-child(2)").css("width","16%");

        $("#attrib-advanced .adminlist.table").css("max-width",960);
        $("#attrib-advanced .adminlist.table th:first-child").css("width","16%");
        $("#attrib-advanced .adminlist.table th:nth-child(3)").css("width","16%");


        $("#historyclear").on("click", function (event) {
            $("#historyclear").html("Wait...")
                .prop('disabled', true);
            $.getJSON("index.php?option=com_ajax&plugin=radicalform&format=json&group=system&admin=2", function (data) {
                location.reload();
            });

            event.preventDefault();
        });

        $("#radicalformcheck").on("click", function (event) {
            var temp=$("#radicalformcheck").html();
            $("#radicalformcheck").html("Wait...")
                                  .prop('disabled', true);

            $.getJSON("index.php?option=com_ajax&plugin=radicalform&format=json&group=system&admin=1", function (data) {
                var output=data.data[0];
                for(var i=0;i<output.length;i++) {
                    var found=false;
                    $("#attrib-advanced .adminlist.table tr td:first-child input").each(function () {

                        if($(this).val()==output[i].chatID) {
                            found=true;
                        }
                    });
                    if(!found) {
                        $("#attrib-advanced th a").trigger("click");
                        $("#attrib-advanced .adminlist.table tr:last-child input:eq(1)").val(output[i].name);
                        $("#attrib-advanced .adminlist.table tr:last-child input:first").val(output[i].chatID);
                    }
                }
                $("#radicalformcheck").html(temp)
                    .prop('disabled', false);

            });

            event.preventDefault();
        });

    });
});

