<?php
    /*
        Steps

        1. Receive get parameters
        2. Check if the forex rates have been updated within the past 2 hours
            2.1. If not, update them from the API
                2.1.1. Read the countries XML to find the symbols for each country we need
                2.1.2. Request the forex rates from the API using GBP as the base currency
            2.2. If they have been, move to step 3
        3. Read countries and their forex rates from the XML file into the simplexmlparser
        4. Convert the amount into GBP
        5. Convert the GBP amount into the requested currency
        6. Return the XML or JSON based on the format param
    */

    // Constants containing file names
    $FOREX_FILE = "forex_rates.xml";
    $FOREX_CURRENCIES_FILE = "forex_currencies.xml";
    $FOREX_UPDATE_TIME_FILE = "forex_update_time.txt";

    // Variables that will contain the GET parameters
    $currency_from;
    $currency_to;
    $amount;
    $format = "xml";

    $update_time;

    // Begin the application process
    convert_currency($FOREX_UPDATE_TIME_FILE, $FOREX_CURRENCIES_FILE, $FOREX_FILE);

    // The main function
    function convert_currency($update_time_file, $forex_currencies, $forex_file) {
        global $format;
        global $update_time;

        get_params();
        $forex_uptodate = check_forex($update_time_file, $forex_currencies);
        if (!$forex_uptodate) {
            update_forex_rates($forex_currencies, $forex_file, $update_time_file);
        }
        $conversion_data = read_forex($forex_file);

        if ($format == "xml" || $format == "json") {
            format_response($update_time, $conversion_data);
        } else {
            throw_error(1400, "Format must be xml or json");
        }
    }

    // Error function
    function throw_error($code, $msg) {
        global $format;

        // Compose the error xml response
        $error_dom = new DOMDocument("1.0", "utf-8");
        $error_dom->formatOutput = true;
        $conv_element = $error_dom->createElement("conv");
        $error_dom->appendChild($conv_element);
        $error_element = $error_dom->createElement("error");
        $conv_element->appendChild($error_element);
        $code_element = $error_dom->createElement("code", $code);
        $error_element->appendChild($code_element);
        $msg_element = $error_dom->createElement("msg", $msg);
        $error_element->appendChild($msg_element);

        // Return the error in the specified format (default XML)
        if ($format == "json") {
            header('Content-type: text/json');
            echo json_encode(simplexml_load_string($error_dom->saveXML()));
        } else {
            header('Content-type: text/xml');
            echo $error_dom->saveXML();
        }

        die(); // Stop script execution
    }

    // Receive the get parameters from the HTTP request
    function get_params() {
        global $currency_from;
        global $currency_to;
        global $amount;
        global $format;

        // Put all the parameter names into an array so we can check if any extra parameters have been added
        $parameters = array_keys($_GET);
        
        // If the format parameter has been passed then save it to a variable
        if (isset($_GET["format"])) {
            $format = $_GET["format"];
        }

        // The same as above for the other parameters
        if (isset($_GET["from"]) && isset($_GET["to"]) && isset($_GET["amnt"])) {
            $currency_from = $_GET["from"];
            $currency_to = $_GET["to"];
            $amount = $_GET["amnt"];
        } else {
            // If any of the above parameters are missing throw a 1000 error
            throw_error(1000, "Required parameter is missing");
        }

        // Check for extra parameters
        $extra_parameters = array_diff($parameters, ["from", "to", "amnt", "format"]);
        if (count($extra_parameters) > 0) {
            // If there are extra parameters, throw a 1100 error
            throw_error(1100, "Parameter not recognized");
        }
    }

    // Sends the response
    function format_response($update_time, $conversion_data) {
        global $amount;
        global $format;

        // Create a new DOMDocument for the response
        $dom = new DOMDocument("1.0", "utf-8");
        $dom->formatOutput = true;

        $base_element = $dom->createElement("conv");
        $dom->appendChild($base_element);

        $start_time_element = $dom->createElement("at", $update_time->format('d M Y H:i'));
        $base_element->appendChild($start_time_element);
        $rate_element = $dom->createElement("rate", round($conversion_data["rate"], 2));
        $base_element->appendChild($rate_element);

        $from_element = $dom->createElement("from");
        $base_element->appendChild($from_element);
        $from_code_element = $dom->createElement("code", $conversion_data["from_symbol"]);
        $from_element->appendChild($from_code_element);
        $from_curr_element = $dom->createElement("curr", $conversion_data["from_name"]);
        $from_element->appendChild($from_curr_element);
        $from_loc_element = $dom->createElement("loc", $conversion_data["from_loc"]);
        $from_element->appendChild($from_loc_element);
        $from_amount_element = $dom->createElement("amnt", $amount);
        $from_element->appendChild($from_amount_element);

        $to_element = $dom->createElement("to");
        $base_element->appendChild($to_element);
        $to_code_element = $dom->createElement("code", $conversion_data["to_symbol"]);
        $to_element->appendChild($to_code_element);
        $to_curr_element = $dom->createElement("curr", $conversion_data["to_name"]);
        $to_element->appendChild($to_curr_element);
        $to_loc_element = $dom->createElement("loc", $conversion_data["to_loc"]);
        $to_element->appendChild($to_loc_element);
        $to_amount_element = $dom->createElement("amnt", round($conversion_data["amount"], 2));
        $to_element->appendChild($to_amount_element);

        // Print the response to the page in the specified format
        if ($format == "json") {
            header('Content-type: text/json');
            echo json_encode(simplexml_load_string($dom->saveXML()));
        } else {
            header("Content-type: text/xml");
            echo $dom->saveXML();
        }

        return $dom;
    }

    // Read the rates file for the specified currencies
    function read_forex($forex_file) {
        global $currency_from;
        global $currency_to;
        global $amount;
        
        // Load the rates XML file
        $dom = new DOMDocument();
        $dom->load($forex_file);

        // Find all the rates entries
        $rates = $dom->getElementsByTagName("forex");
        $currency_from_rate = 0;
        $currency_to_rate = 0;
        $currency_from_symbol = "";
        $currency_to_symbol = "";
        $currency_from_name = "";
        $currency_to_name = "";
        $currency_from_loc = "";
        $currency_to_loc = "";
        // Loop through and save the information for the specified currencies
        foreach ($rates as $rate) {
            $current_symbol = $rate->getAttribute("symbol");
            $current_rate = $rate->getAttribute("rate");
            $current_name = $rate->getAttribute("name");
            $current_loc = $rate->getAttribute("loc");
            if ($current_symbol == $currency_from) {
                $currency_from_rate = floatval($current_rate);
                $currency_from_symbol = $current_symbol;
                $currency_from_name = $current_name;
                $currency_from_loc = $current_loc;
            }
            if ($current_symbol == $currency_to) {
                $currency_to_rate = floatval($current_rate);
                $currency_to_symbol = $current_symbol;
                $currency_to_name = $current_name;
                $currency_to_loc = $current_loc;
            }
        }
        
        // If one of the currencies isn't found, throw a 1200 error
        if ($currency_from_rate == 0 || $currency_to_rate == 0) {
            throw_error(1200, "Currency type not recognized");
        }

        // If the currency amount is not a decimal or whole number, throw a 1300 error
        if (strlen(substr(strrchr($amount, "."), 1)) > 2 || !is_numeric($amount) || $amount < 0) {
            throw_error(1300, "Currency amount must be a decimal number");
        }

        // Convert currency_from into GBP: $currency_from / GBP_rate
        // Then convert the GBP value into the required currency
        $conversion_rate = $currency_to_rate / $currency_from_rate;
        $converted_value = $amount * $conversion_rate;
        // Return all the information required in the response
        return ["amount" => $converted_value,
                "rate" => $conversion_rate,
                "from_symbol" => $currency_from_symbol,
                "to_symbol" => $currency_to_symbol,
                "from_name" => $currency_from_name,
                "to_name" => $currency_to_name,
                "from_loc" => $currency_from_loc,
                "to_loc" => $currency_to_loc];
    }

    // Check if the forex rates have been updated within the last 2 hours
    function check_forex($update_time_file) {
        global $update_time;

        $current_time = new DateTime("now", new DateTimeZone("Europe/London"));
        if (file_exists($update_time_file)) {
            // Open the file containing the time last updated
            $file = fopen($update_time_file, "r");
            if (filesize($update_time_file) == 0) return false; // If the file is empty we need to update
            $last_updated_time_string = fread($file, filesize($update_time_file));
            // Convert the update time string into a DateTime object
            $last_updated_time = new DateTime($last_updated_time_string, new DateTimeZone('Europe/London'));
            // Check if difference is more than 2 hours
            $difference = date_diff($last_updated_time, $current_time, true);
            if ($difference->{"h"} >= 2) {
                return false;

            } else {
                $update_time = $last_updated_time;
                return true;
            }
        } else {
            return false;
        }
    }

    // Update the forex rates in the forex_rates.xml file
    function update_forex_rates($forex_currencies, $forex_file, $update_time_file) {
        global $update_time;

        $currencies = [];

        $dom = new DOMDocument();
        $dom->load($forex_currencies);
        // Add all currencies and their information in the forex_currencies.xml file to an array
        foreach ($dom->getElementsByTagName("currency") as $currency) {
            $symbol = $currency->getAttribute("symbol");
            $name = $currency->getAttribute("name");
            $loc = $currency->getAttribute("loc");
            $live = $currency->getAttribute("live");
            array_push($currencies, ["symbol" => $symbol, "name" => $name, "loc" => $loc, "live" => $live]);
        }

        $api_base_url = "https://api.exchangeratesapi.io/latest?base=GBP&symbols=";
        // Compose API request URL
        foreach ($currencies as $currency) {
            $api_base_url .= $currency["symbol"] . ",";
        }
        $api_base_url = substr($api_base_url, 0, strlen($api_base_url) - 1);

        // Make API request and throw a 1200 error if it fails as the rate isn't listed by the API
        $json_contents = @file_get_contents($api_base_url) or throw_error(1200, "Currency type not recognized");
        
        $json_decoded = json_decode($json_contents);
        $rates = $json_decoded->{"rates"};
        
        // Create a DOMDocument object to write the rates xml file
        $rates_dom = new DOMDocument("1.0", "utf-8");
        $rates_dom->formatOutput = true;

        $base_element = $rates_dom->createElement("rates");
        $rates_dom->appendChild($base_element);
        foreach ($rates as $symbol => $rate) {
            $current_name = "";
            $current_loc = "";
            $live = "";
            foreach ($currencies as $currency) {
                if ($currency["symbol"] == $symbol) {
                    $current_name = $currency["name"];
                    $current_loc = $currency["loc"];
                    $live = $currency["live"];
                }
            }
            // Only add the currency to the rates file if it is live
            if ($live == "1") {
                $current_element = $rates_dom->createElement("forex");
                $current_element->setAttribute("symbol", $symbol);
                $current_element->setAttribute("rate", $rate);
                $current_element->setAttribute("name", $current_name);
                $current_element->setAttribute("loc", $current_loc);
                $base_element->appendChild($current_element);
            }
        }
        $rates_dom->save($forex_file);
        
        // Update the time in the update time file
        $update_time = new DateTime("now", new DateTimeZone("Europe/London"));
        $file = fopen($update_time_file, "w");
        fwrite($file, $update_time->format("Y-m-d H:i:s"));
        fclose($file);
    }
?>