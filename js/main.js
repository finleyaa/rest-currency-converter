$(document).ready(function () {
    $("form").on("submit", function () {
        var form_data = {
            cur: $("select[name=cur]").val(),
            action: $("input[name=action]:checked").val(),
        };

        $.ajax({
            type: "GET",
            url: "update.php/",
            data: form_data,
            dataType: "xml",
            encode: true,
        }).done(function (data) {
            $("textarea[name=response]").html(data.documentElement.outerHTML);
        });

        return false;
    });
});
