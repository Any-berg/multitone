<?php
// DTMF generator
// based on https://aggemam.dk/code/dtmf by Christian Schmidt 

//---configuration---

foreach ($argv as $arg) {
  $e=explode("=",$arg);
  if(count($e)==2)
    $_GET[$e[0]]=$e[1];
}

$channels = isset($_GET['ch']) ? $_GET['ch'] : 1;

// samples per second
$sample_rate = isset($sample_rate) ? intval($sample_rate) : 8000*$channels;

// signal length in milliseconds
$signal_length = isset($signal_length) ? intval($signal_length) : 100;

// break between signals in milliseconds
$break_length = isset($break_length) ? intval($break_length) : 100;

// pause length in milliseconds - pause character is ','
$pause_length = isset($pause_length) ? intval($pause_length) : 500;

// amplitude of wave file in the range 0-64
$amplitude = isset($amplitude) ? intval($amplitude) : 64;

# $upper_case and $lower_case specifies how letters in upper and lower case
# are treated. Following values are recognized: 'key', 'hex', 'abc' or false.
#
# 'key' means that the letters specify signals A, B, C and D, for      1 2 3 A
# which some phones have special keys. See standard keypad on right:   4 5 6 B
#                                                                      7 8 9 C
# 'hex' means that letters A-F are hexadecimal numbers (i.e. 10-15).   * 0 # D
#
# 'abc' means that letters correspond to numbers, e-g. 1-800-FOO-BAR.
#
# false means that letters of the specified case cannot be used.
#
$upper_case = isset($upper_case) ? $upper_case : 'hex';
$lower_case = isset($lower_case) ? $lower_case : 'key';

//---end of configuration---

$alnum = $_GET['n'];

//build frequency tables
$lowfreqs = array(697, 770, 852, 941);
$highfreqs = array(1209, 1336, 1477, 1633);
$signals = array(
  '1', '2', '3', 'A',
  '4', '5', '6', 'B',
  '7', '8', '9', 'C',
  '*', '0', '#', 'D');
$i = 0; foreach ($signals as $signal) {
  $low[$signal] = $lowfreqs[(int) ($i / 4)] / $sample_rate * 2 * M_PI;
  $high[$signal] = $highfreqs[$i % 4] / $sample_rate * 2 * M_PI;
  $i++;
}
$low['_'] = 0;

// map alphanumeric input to standardised keypad layout (ITU E.161/ISO 9995-8)
$alphabet    = 'abcdefghijklmnopqrstuvwxyz';
$abc2digits  = '22233344455566677778889999';

// map hexadecimal values to pins Q1-Q4 of module MT8870/CM8870 (D0-D3 @ HT9170)
$hexadecimal = '0abcdef'; // skipped values 1-9 already have correct binary form
$hex2keys    = 'D0*#ABC';

if ($lower_case == 'abc')
  $num = strtr($alnum, $alphabet, $abc2digits);
else if ($lower_case == 'hex')
  $num = strtr($alnum, $hexadecimal, $hex2keys);

if ($upper_case == 'abc')
  $num = strtr($alnum, strtoupper($alphabet), $abc2digits);
else if ($upper_case == 'hex')
  $num = strtr($alnum, strtoupper($hexadecimal), $hex2keys);

$alnum = preg_replace('/[^0-9a-z#*_]/i', '', $alnum);
$num = strtoupper(preg_replace('/[^0-9a-d#*_]/i', '', $num));

$output = '';

for ($i = 0; $i < strlen($num); $i+=$channels) {
  $signal = substr($num, $i, $channels);
  if (preg_match('/^_+$/', $signal))
    $output .= str_repeat("\0", $channels*$pause_length / 1000 * $sample_rate);
  else if (!isset($low[$signal[0]]))
    break; //an invalid character has been encountered
  else {
    for ($j = 0; $j < $signal_length / 1000 * $sample_rate; $j++)
      for ($k = 0; $k < $channels; $k++)
        $output .= chr(floor($amplitude * (sin($j * $low[$signal[$k]]) +
                                           sin($j * $high[$signal[$k]]))));
    $output .= str_repeat("\0", $channels*$break_length / 1000 * $sample_rate);
  }
}

//make sure that all output contains at least 1 byte excl. the header
if (strlen($output) == 0) {
  $output = "\0";
}

//generate Au sound file: https://en.wikipedia.org/wiki/Au_file_format
$output = ".snd" .              //"magic number"
  pack('N', 24) .               //data offset
  pack('N', strlen($output)) .  //data size (0xffffffff = unknown)
  pack('N', 2) .                //encoding (2->8bit, 3->16bit; both linear PCM)
  pack('N', $sample_rate) .     //sample rate
  pack('N', $channels) .        //channels
  $output;

header('Content-Length: ' . strlen($output));
header('Content-Type: audio/basic');
header('Content-Disposition: filename="' . $alnum . '.au"');

print $output;
