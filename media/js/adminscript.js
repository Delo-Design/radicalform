jQuery(document).ready(function () {
    jQuery(function ($) {
        $("#historyclear").on("click", function (event) {
            $("#historyclear").html("Wait...")
                .prop('disabled', true);
            $.getJSON("/administrator/index.php?option=com_ajax&plugin=radicalform&format=json&group=system&admin=2", function (data) {
                location.reload();
            });

            event.preventDefault();
        });
        $("#radicalformcheck").on("click", function (event) {
            $("#radicalformcheck").html("Wait...")
                                  .prop('disabled', true);
            $.getJSON("/administrator/index.php?option=com_ajax&plugin=radicalform&format=json&group=system&admin=1", function (data) {
                location.reload();
            });

            event.preventDefault();
        });

    });
});

