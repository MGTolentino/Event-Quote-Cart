<?php
/**
 * Compile .po file to .mo binary format
 * This script converts the Spanish translation file to binary format
 */

// Simple .po to .mo converter
function compile_po_to_mo($po_file, $mo_file) {
    $translations = array();
    $current_msgid = '';
    $current_msgstr = '';
    $in_msgid = false;
    $in_msgstr = false;
    
    $lines = file($po_file);
    
    foreach ($lines as $line) {
        $line = trim($line);
        
        // Skip comments and empty lines
        if (empty($line) || $line[0] === '#') {
            continue;
        }
        
        if (strpos($line, 'msgid "') === 0) {
            // Save previous translation if exists
            if (!empty($current_msgid) && !empty($current_msgstr)) {
                $translations[$current_msgid] = $current_msgstr;
            }
            
            // Start new msgid
            $current_msgid = substr($line, 7, -1);
            $current_msgstr = '';
            $in_msgid = true;
            $in_msgstr = false;
        } elseif (strpos($line, 'msgstr "') === 0) {
            // Start msgstr
            $current_msgstr = substr($line, 8, -1);
            $in_msgid = false;
            $in_msgstr = true;
        } elseif ($line[0] === '"' && $line[strlen($line)-1] === '"') {
            // Continuation line
            $content = substr($line, 1, -1);
            if ($in_msgid) {
                $current_msgid .= $content;
            } elseif ($in_msgstr) {
                $current_msgstr .= $content;
            }
        }
    }
    
    // Save last translation
    if (!empty($current_msgid) && !empty($current_msgstr)) {
        $translations[$current_msgid] = $current_msgstr;
    }
    
    // Create binary .mo file
    $mo_data = create_mo_file($translations);
    file_put_contents($mo_file, $mo_data);
    
    return count($translations);
}

function create_mo_file($translations) {
    // Remove empty msgid (header)
    unset($translations['']);
    
    $count = count($translations);
    $ids = array_keys($translations);
    $strings = array_values($translations);
    
    // Calculate offsets
    $header_size = 28;
    $table_size = $count * 8;
    $ids_offset = $header_size + 2 * $table_size;
    $strings_offset = $ids_offset;
    
    foreach ($ids as $id) {
        $strings_offset += strlen($id) + 1;
    }
    
    // Create binary data
    $mo = '';
    
    // Magic number
    $mo .= pack('L', 0x950412de);
    
    // Version
    $mo .= pack('L', 0);
    
    // Number of strings
    $mo .= pack('L', $count);
    
    // Offset of original strings table
    $mo .= pack('L', $header_size);
    
    // Offset of translated strings table
    $mo .= pack('L', $header_size + $table_size);
    
    // Hash table size (unused)
    $mo .= pack('L', 0);
    
    // Hash table offset (unused)
    $mo .= pack('L', 0);
    
    // Original strings table
    $offset = $ids_offset;
    foreach ($ids as $id) {
        $mo .= pack('L', strlen($id));
        $mo .= pack('L', $offset);
        $offset += strlen($id) + 1;
    }
    
    // Translated strings table
    $offset = $strings_offset;
    foreach ($strings as $string) {
        $mo .= pack('L', strlen($string));
        $mo .= pack('L', $offset);
        $offset += strlen($string) + 1;
    }
    
    // Original strings
    foreach ($ids as $id) {
        $mo .= $id . "\0";
    }
    
    // Translated strings
    foreach ($strings as $string) {
        $mo .= $string . "\0";
    }
    
    return $mo;
}

// Run the conversion
$po_file = __DIR__ . '/languages/event-quote-cart-es_ES.po';
$mo_file = __DIR__ . '/languages/event-quote-cart-es_ES.mo';

if (file_exists($po_file)) {
    $count = compile_po_to_mo($po_file, $mo_file);
    echo "Successfully compiled $count translations from .po to .mo\n";
    echo "Created: $mo_file\n";
} else {
    echo "Error: .po file not found at $po_file\n";
}