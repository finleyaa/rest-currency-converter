<!DOCTYPE html>
<html lang="en">
    <head>
        <title>Form Interface CRUD</title>
        <link rel="stylesheet" href="css/main.css"/>
        <link rel="preconnect" href="https://fonts.gstatic.com">
        <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;700&display=swap" rel="stylesheet">
    </head>
    <body>
        <h1>Form Interface for POST, PUT and DELETE</h1>
        <form action="update.php" method="GET" id="ajax_form">
            <div>
                <label for="action">Action</label>
                <input type="radio" name="action" value="post" id="action"/>POST
                <input type="radio" name="action" value="put" id="action"/>PUT
                <input type="radio" name="action" value="del" id="action"/>DEL
            </div>

            <div>
                <label for="cur">Currency</label>
                <select name="cur" id="cur">
                    <?php
                        $currencycodes_dom = new DOMDocument();
                        $currencycodes_dom->load("forex_currencies.xml");
                        $all_currencies = $currencycodes_dom->getElementsByTagName("currency");
                        foreach ($all_currencies as $curr) {
                            $symbol = $curr->getAttribute("symbol");
                            $name = $curr->getAttribute("name");
                            echo "<option value='$symbol'>$name ($symbol)</option>";
                        }
                    ?>
                </select>
            </div>

            <button type="submit">Submit</button>

            <div id="response_div">
                <label for="response">XML Response</label>
                <textarea id="response" name="response" cols="100" rows="25"></textarea>
            </div>
        </form>
    </body>
    <script src="js/jquery-3.5.1.min.js"></script>
    <script src="js/main.js"></script>
</html>