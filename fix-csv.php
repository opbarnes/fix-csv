#!/usr/bin/env php
<?php

function usage($handle, $progname) {
    $usage_out = "usage: {$progname} <filename> -c <column to add quote> [--rmleadspaces] [-h]";
    fwrite($handle, $usage_out . PHP_EOL);
}

main($argv);
exit(0);

function err_exit($error_text) {
    fwrite(STDERR, $error_text . "\n");
    exit(1);
}

function debug($debug_text) {
    fwrite(STDOUT, "DEBUG: " . $debug_text . "\n");
}

function main($argv) {
    $remove_leading = false;
    $input_file = '';
    $progname = '';
    $col = 0;
    $debug = false;

    if (empty($argv)) {
        usage(STDERR, 'fix-csv');
        exit(1);
    }

    $progname = array_shift($argv);
    if (substr($progname, 0, 2) === './') {
        $progname = substr($progname, 2);
    }

    // Options parsing
    while (count($argv)) {
        $param = array_shift($argv);
        switch ($param) {
			case '--rmleadspaces':
				$remove_leading = true;
				break;
            case '--debug':
				$debug = true;
				break;
            default:
                if ($input_file !== '') {
                    err_exit("Unknown option: {$param}");
                }
                else {
                    $input_file = $param;
                }
                break;
            case '-c':
                $col = intval(array_shift($argv));
				break;
            case '-h':
				usage(STDOUT, $progname);
				exit(1);
		}
    }

    if ( ($input_file === '') ||
         (!file_exists($input_file)) ) {
             err_exit("File does not exist.");
    }

    $csv = array_map('str_getcsv', file($input_file));
    if (count($csv) < 1) {
        err_exit("File empty.");
    }

    $first = array_shift($csv);
    $num_cols = count($first);
    if ($col > ($num_cols - 1)) {
        err_exit("Specified column is larger than number of columns ($num_cols) in file.");
    }

    $out = fopen('php://output', 'w');
    if ($remove_leading) {
        foreach ($first as &$subitem) {
            if (substr($subitem, 0, 1) === ' ') {
                $subitem = substr($subitem, 1);
            }
        }
    }
    fputcsv($out, $first);

    foreach($csv as $item) {
        $new_item = array();
        $first_size = count($first);
        if (count($item) > $first_size) {
            $line_size = count($item);
            foreach ($item as $subitem) {
                if (count($new_item) == ($col + 1)) {
                    // We're woring on the "column to add quote" for the
                    // second or later time for this row
                    if ($line_size > $first_size) {
                        // We still have more columns than we are allowed, so
                        // append to tne "column to add quote" column
                        $old = array_pop($new_item);
                        $tmp = $old . $subitem;
                        array_push($new_item, $tmp);
                        $line_size--;
                        if ($debug) {
                            debug("Appending to identified column: '{$old}' | '{$subitem}' [line size: {$line_size}, expected: {$first_size}]");
                        }    
                    }
                    else {
                        array_push($new_item, $subitem);
                        if ($debug) {
                            debug("Row is now right size, adding next column: {$subitem} [line size: {$line_size}, expected: {$first_size}]");
                        }
                    }
                }
                else {
                    // We're woring on the "column to add quote" for the
                    // first time for this row
                    if ( (count($new_item) == $col) && ($debug) ) {
                        debug("Working on identified column ({$col}): {$subitem} [line size: {$line_size}, expected: {$first_size}]");
                    }
                    array_push($new_item, $subitem);
                }
            }
        }
        else {
            $new_item = $item;
        }

        if ($remove_leading) {
            foreach ($new_item as &$subitem) {
                if (substr($subitem, 0, 1) === ' ') {
                    $subitem = substr($subitem, 1);
                }
            }
        }
        fputcsv($out, $new_item);
    }
    fclose($out);
    exit(0);
}

?>