<?php

    // Constants containing file names
    $FOREX_FILE = "forex_rates.xml";
    $FOREX_CURRENCIES_FILE = "forex_currencies.xml";
    $FOREX_UPDATE_TIME_FILE = "forex_update_time.txt";

    // Variables for the GET params
    $action = "NULL";
    $cur = "";

    // Run the main function
    perform_action();

    // Error function
    function throw_error($code, $msg) {
        global $action;

        // Compose the error xml response
        $error_dom = new DOMDocument("1.0", "utf-8");
        $error_dom->formatOutput = true;
        $action_element = $error_dom->createElement("action");
        $action_element->setAttribute("type", $action);
        $error_dom->appendChild($action_element);
        $error_element = $error_dom->createElement("error");
        $action_element->appendChild($error_element);
        $code_element = $error_dom->createElement("code", $code);
        $error_element->appendChild($code_element);
        $msg_element = $error_dom->createElement("msg", $msg);
        $error_element->appendChild($msg_element);

        // Return the error in XML
        header('Content-type: text/xml');
        echo $error_dom->saveXML();

        die(); // Stop script execution
    }

    // The main function
    function perform_action() {
        global $action;
        global $FOREX_FILE;
        global $FOREX_CURRENCIES_FILE;

        get_params();

        if ($action == "post") {
            action_post($FOREX_FILE);
        } elseif ($action == "put") {
            action_put($FOREX_FILE, $FOREX_CURRENCIES_FILE);
        } elseif ($action == "del") {
            action_del($FOREX_FILE, $FOREX_CURRENCIES_FILE);
        }
    }
    
    // Receive the GET parameters
    function get_params() {
        global $action;
        global $cur;

        // If action isn't set, return a 2000 error, otherwise save it in a variable
        if (isset($_GET["action"])) {
            $action = $_GET["action"];
        } else {
            throw_error(2000, "Action not recognized or is missing");
        }
        // If cur isn't set, return a 2000 error, otherwise save it in a variable
        if (isset($_GET["cur"])) {
            $cur = $_GET["cur"];
        } else {
            throw_error(2100, "Currency code in wrong format or is missing");
        }

        // Check if the action is post, put or del otherwise throw a 2000 error
        $actions = ["post", "put", "del"];        
        if (!in_array($action, $actions)) {
            throw_error(2000, "Action not recognized or is missing");
        }
        // Check the format of the cur and throw a 2100 error if it is incorrect
        if (strlen($cur) != 3 || is_numeric($cur)) {
            throw_error(2100, "Currency code in wrong format or is missing");
        }

        // Cannot update the base currency so throw a 2400 error
        if (strtoupper($cur) == "GBP") {
            throw_error(2400, "Cannot update base currency");
        }
    }

    // Post function
    function action_post($forex_file) {
        global $action;
        global $cur;

        // API request URL
        $api_base_url = "https://api.exchangeratesapi.io/latest?base=GBP&symbols=$cur";

        // Make the API request and throw a 2300 error if it fails
        $json_contents = @file_get_contents($api_base_url) or throw_error(2300, "No rate listed for the currency");
        $json_decoded = json_decode($json_contents);

        $new_rate = $json_decoded->{"rates"}->{$cur};
        $old_rate = 0;
        $name = "";
        $loc = "";
        $current_time = new DateTime("now", new DateTimeZone("Europe/London"));

        $rates_dom = new DOMDocument();
        $rates_dom->load($forex_file);

        // Find the selected currency in the rates file and update it
        $all_rates = $rates_dom->getElementsByTagName("forex");
        foreach ($all_rates as $rate) {
            if ($rate->getAttribute("symbol") == $cur) {
                $old_rate = $rate->getAttribute("rate");
                $rate->setAttribute("rate", $new_rate);
                $name = $rate->getAttribute("name");
                $loc = $rate->getAttribute("loc");
            }
        }

        // If the currency isn't found in the rates file, return a 2200 error
        if ($old_rate == 0 && $name == "") {
            throw_error(2200, "Currency code not found for update");
        }

        $rates_dom->save($forex_file);

        // Format and return the XML response for the post request
        $response_dom = new DOMDocument("1.0", "UTF-8");
        $response_dom->formatOutput = true;

        $action_element = $response_dom->createElement("action");
        $action_element->setAttribute("type", "post");
        $response_dom->appendChild($action_element);
        $at_element = $response_dom->createElement("at", $current_time->format("d M Y H:i"));
        $action_element->appendChild($at_element);
        $rate_element = $response_dom->createElement("rate", $new_rate);
        $action_element->appendChild($rate_element);
        $oldrate_element = $response_dom->createElement("old_rate", $old_rate);
        $action_element->appendChild($oldrate_element);

        $curr_element = $response_dom->createElement("curr");
        $action_element->appendChild($curr_element);
        $code_element = $response_dom->createElement("code", $cur);
        $curr_element->appendChild($code_element);
        $name_element = $response_dom->createElement("name", $name);
        $curr_element->appendChild($name_element);
        $loc_element = $response_dom->createElement("loc", $loc);
        $curr_element->appendChild($loc_element);

        header("Content-type: text/xml");
        echo $response_dom->saveXML();
    }

    // Put function
    function action_put($forex_file, $forex_currencies) {
        global $action;
        global $cur;

        // API request URL
        $api_base_url = "https://api.exchangeratesapi.io/latest?base=GBP&symbols=$cur";

        // Make the API request and throw a 2300 error if it fails
        $json_contents = @file_get_contents($api_base_url) or throw_error(2300, "No rate listed for the currency");
        $json_decoded = json_decode($json_contents);

        $rate = $json_decoded->{"rates"}->{$cur};
        $name = "";
        $loc = "";
        $current_time = new DateTime("now", new DateTimeZone("Europe/London"));

        $dom = new DOMDocument();
        $dom->load($forex_currencies);

        $replacing_entry = false;

        // Find the currency in the currencies xml file and set it to live
        foreach ($dom->getElementsByTagName("currency") as $currency) {
            $symbol = $currency->getAttribute("symbol");
            $live = $currency->getAttribute("live");
            if ($symbol == $cur) {
                $name = $currency->getAttribute("name");
                $loc = $currency->getAttribute("loc");
                // If the currency is already live, we are going to replace the rate in the rates file
                if ($live == "1") {
                    $replacing_entry = true;
                } else {
                    $currency->setAttribute("live", "1");
                }
                break;
            }
        }
        $dom->save($forex_currencies);

        $rates_dom = new DOMDocument();
        $rates_dom->load($forex_file);
        $rates_dom->formatOutput = true;
        $rates_dom->preserveWhiteSpace = false;

        // If the rate was already live
        if ($replacing_entry) {
            $all_rates = $rates_dom->getElementsByTagName("forex");

            // Find the currency entry in the rates file and update the rate
            foreach ($all_rates as $rate_to_update) {
                if ($rate_to_update->getAttribute("symbol") == $cur) {
                    $rate_to_update->setAttribute("rate", $rate);
                    $rate_to_update->setAttribute("name", $name);
                    $rate_to_update->setAttribute("loc", $loc);
                }
            }
        } else { // If the rate was not live before
            // Add a new entry for the currency to the rates file
            $new_entry = $rates_dom->createElement("forex");
            $new_entry->setAttribute("symbol", $cur);
            $new_entry->setAttribute("rate", $rate);
            $new_entry->setAttribute("name", $name);
            $new_entry->setAttribute("loc", $loc);

            $rates_dom->getElementsByTagName("rates")->item(0)->appendChild($new_entry);
        }

        $rates_dom->save($forex_file);

        // Format and return the XML response for the put request
        $response_dom = new DOMDocument("1.0", "UTF-8");
        $response_dom->formatOutput = true;

        $action_element = $response_dom->createElement("action");
        $action_element->setAttribute("type", "put");
        $response_dom->appendChild($action_element);
        $at_element = $response_dom->createElement("at", $current_time->format("d M Y H:i"));
        $action_element->appendChild($at_element);
        $rate_element = $response_dom->createElement("rate", $rate);
        $action_element->appendChild($rate_element);

        $curr_element = $response_dom->createElement("curr");
        $action_element->appendChild($curr_element);
        $code_element = $response_dom->createElement("code", $cur);
        $curr_element->appendChild($code_element);
        $name_element = $response_dom->createElement("name", $name);
        $curr_element->appendChild($name_element);
        $loc_element = $response_dom->createElement("loc", $loc);
        $curr_element->appendChild($loc_element);

        header("Content-type: text/xml");
        echo $response_dom->saveXML();
    }

    // Del function
    function action_del($forex_file, $forex_currencies) {
        global $action;
        global $cur;

        $rates_dom = new DOMDocument();
        $rates_dom->load($forex_file);

        $found_curr = false;
        $current_time = new DateTime("now", new DateTimeZone("Europe/London"));

        $all_rates = $rates_dom->getElementsByTagName("forex");
        foreach ($all_rates as $rate) {
            if ($rate->getAttribute("symbol") == $cur) {
                $found_curr = true;
                // Remove record from rates xml file
                $rate->parentNode->removeChild($rate);
            }
        }
        $rates_dom->save($forex_file);

        // If the rate is not in the rates file, throw a 2200 error
        if (!$found_curr) {
            throw_error(2200, "Currency code not found for update");
        }

        $curr_dom = new DOMDocument();
        $curr_dom->load($forex_currencies);

        foreach ($curr_dom->getElementsByTagName("currency") as $currency) {
            if ($currency->getAttribute("symbol") == $cur) {
                // Set to not live in currencies xml file
                $currency->setAttribute("live", "0");
            }
        }

        $curr_dom->save($forex_currencies);

        // Format and return the XML response for the del request
        $response_dom = new DOMDocument("1.0", "UTF-8");
        $response_dom->formatOutput = true;

        $action_element = $response_dom->createElement("action");
        $action_element->setAttribute("type", "del");
        $response_dom->appendChild($action_element);
        $at_element = $response_dom->createElement("at", $current_time->format("d M Y H:i"));
        $action_element->appendChild($at_element);
        $code_element = $response_dom->createElement("code", $cur);
        $action_element->appendChild($code_element);

        header("Content-type: text/xml");
        echo $response_dom->saveXML();
    }

?>