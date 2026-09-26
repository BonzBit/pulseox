<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
  <head>
  <meta name="viewport" content="maximum-scale=1.6,width=320,user-scalable=false" />
  <meta http-equiv="refresh" content="5">
  <meta http-equiv="content-type" content="text/html; charset=utf-8"/>

  <title>Pulse Ox Status</title>
<link rel="icon" type="image/x-icon" href="/med.ico">

<?php

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

try
{
$dbh = new PDO('sqlite:' . __DIR__ . '/pulseox.db');
$dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// query result will look like the following:
// 11/17/12 06:47:24 SPO2=092% BPM=128
// Check if there are any records first
$count_query = $dbh->prepare("SELECT COUNT(*) from pulseox");
$count_query->execute();
$record_count = $count_query->fetchColumn();

if ($record_count == 0) {
    throw new Exception("No records found in database");
}

// Get all columns including any exception data
$query=$dbh->prepare("SELECT stampdate, stamptime, spo2, bpm, * from pulseox order by id desc limit 1");
$query->execute();
$result=$query->fetch(PDO::FETCH_ASSOC);

if (!$result) {
    throw new Exception("Failed to fetch latest record");
}

// Safely extract data with null checks
$chart_bpm_data = isset($result['bpm']) ? $result['bpm'] : '';
$chart_spo2_data = isset($result['spo2']) ? substr($result['spo2'], 0, -1) : ''; // strip off the last '%' character

$value_bpm_data = strlen($chart_bpm_data) >= 7 ? substr($chart_bpm_data, 4, 3) : '---';
$value_spo2_data = strlen($chart_spo2_data) >= 8 ? substr($chart_spo2_data, 5, 3) : '---';

// Try to extract exception data - look for any column that might contain it
$value_exc_data = '';
foreach ($result as $key => $value) {
    if (is_string($value) && strpos($value, 'EXC=') !== false && strlen($value) >= 10) {
        $value_exc_data = substr($value, 7, 3);
        break;
    }
}

// Default to empty if no exception data found
if (empty($value_exc_data)) {
    $value_exc_data = '000';
}

$excbit1 = substr($value_exc_data, 0, 1);
$excbit2 = substr($value_exc_data, 1, 1);
$excbit3 = substr($value_exc_data, 2, 1);

$excbit1binary = base_convert($excbit1, 16, 2);
$excbit2binary = base_convert($excbit2, 16, 2);
$excbit3binary = base_convert($excbit3, 16, 2);

$exception_msg = "";
//settype($exception_msg, "string");



//echo "excbit1binary is " . $excbit1binary;
//echo "excbit2binary is " . $excbit2binary;
//echo "excbit3binary is " . $excbit3binary;

$hide = TRUE;

function is_bitflag_set($val, $flag)
{
    return ((bindec($val) & $flag) === $flag);
}

if(is_bitflag_set($excbit1binary, 4))
{
    $exception_msg .= "Low Signal IQ, ";
}
if(is_bitflag_set($excbit2binary, 1))
{
    $exception_msg .= "Interference, ";
}
if(is_bitflag_set($excbit2binary, 2))
{
    $exception_msg .= "Sensor Off, ";
}
if(is_bitflag_set($excbit2binary, 4))
{
    $exception_msg .= "Ambient Light, ";
}
if(is_bitflag_set($excbit2binary, 8))
{
    $exception_msg .= "Unrecognized Sensor, ";
}
if(is_bitflag_set($excbit3binary, 1))
{
    $exception_msg .= "No Sensor, ";
}
if(is_bitflag_set($excbit3binary, 2))
{
    $exception_msg .= "Defective Sensor, ";
}
if(is_bitflag_set($excbit3binary, 4))
{
    $exception_msg .= "Low Perfusion, ";
}
if(is_bitflag_set($excbit3binary, 8))
{
    $exception_msg .= "Pulse Search, ";
}

if (isset($exception_msg) and strlen($exception_msg) > 1)
{
    $exception_msg = substr($exception_msg, 0, -2);
    $hide = FALSE;
//    echo "Exception Message: " . $exception_msg;
}

//echo "Exception Data: " . $value_exc_data; 

if (!$value_bpm_data)
{
   $value_bpm_data = "---";
}

if (!$value_spo2_data)
{
   $value_spo2_data = "---";
}

//$response["BPM"] = $value_bpm_data;
//$response["SPO2"] = $value_spo2_data;
//echo json_encode($response);


//$last_update = $result[0] . " @ " . $result[1];

//retrieve a bunch of BPMs and SPO2s for averaging - result will look like:
// 83.8 99.5
$query=$dbh->prepare("SELECT round(avg(substr(bpm,5,3)),1), round(avg(substr(spo2,6,3)),1) from pulseox where bpm != 'BPM=---' and spo2 != 'SPO2=---%' and id > ((select max(id) from pulseox) - 3600)");
$query->execute();
$result=$query->fetch();
$chart_avg_bpm_data = $result[0];
$chart_avg_spo2_data = $result[1];


// Use current time for chart data instead of device timestamps
$q=$dbh->prepare("SELECT bpm, spo2 from pulseox where bpm != 'BPM=---' and spo2 != 'SPO2=---%' and id > ((select max(id) from pulseox) - 1200) order by id asc");
$q->execute();

 $data = "var data = new google.visualization.DataTable();\n\r"
 ."data.addColumn('timeofday', 'Time');\n\r"
 ."data.addColumn('number', 'BPM');\n\r"
 ."data.addColumn('number', 'SPO2');\n\r\n\r"
 ."data.addRows([\n\r";

// Calculate time points based on current time going backwards
$current_time = time();
$records = $q->fetchAll(PDO::FETCH_ASSOC);
$total_records = count($records);

foreach ($records as $index => $res) {
        // Calculate time for this record (going back in time from now)
        $seconds_ago = ($total_records - $index - 1) * 5; // assuming 5 second intervals
        $record_time = $current_time - $seconds_ago;
        $time_parts = explode(':', date('H:i:s', $record_time));
        $tmpstamptime = "[" . implode(', ', $time_parts) . "]";
        
        $tmpbpm = (int)substr($res["bpm"],4,3);
        $tmpspo2 = (int)substr($res["spo2"],5,3);
        $data = $data."  [".$tmpstamptime.", ".$tmpbpm.", ".$tmpspo2."],\n\r";
}
$data = substr($data,0,-3)." ]);\n\r";

//Print data to check if data from database is loaded
//echo $data;

$dbh = null;

}
catch(PDOException $e)
{
    error_log('Database Error: ' . $e->getMessage());
    echo '<div style="color: red; font-weight: bold;">Database Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
    // Set default values to prevent undefined variable errors
    $value_bpm_data = '---';
    $value_spo2_data = '---';
    $chart_avg_bpm_data = 0;
    $chart_avg_spo2_data = 0;
    $data = "var data = new google.visualization.DataTable();\ndata.addColumn('timeofday', 'Time');\ndata.addColumn('number', 'BPM');\ndata.addColumn('number', 'SPO2');\ndata.addRows([]);";
    $exception_msg = 'Database connection failed';
    $hide = FALSE;
}
catch(Exception $e)
{
    error_log('Application Error: ' . $e->getMessage());
    echo '<div style="color: red; font-weight: bold;">Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
    // Set default values to prevent undefined variable errors
    $value_bpm_data = '---';
    $value_spo2_data = '---';
    $chart_avg_bpm_data = 0;
    $chart_avg_spo2_data = 0;
    $data = "var data = new google.visualization.DataTable();\ndata.addColumn('timeofday', 'Time');\ndata.addColumn('number', 'BPM');\ndata.addColumn('number', 'SPO2');\ndata.addRows([]);";
    $exception_msg = $e->getMessage();
    $hide = FALSE;
}

?>

    <script type="text/javascript" src="http://www.google.com/jsapi"></script>
    <script type="text/javascript">
      google.load('visualization', '1', {packages: ['gauge', 'corechart', 'line']});
    </script>
    <script type="text/javascript">
      function drawVisualization() {
        // Create and populate the data table.
		var bpm_data = google.visualization.arrayToDataTable([
			['Label', 'Value'],
  			['BPM', <?php echo $value_bpm_data; ?>]]);

		var spo2_data = google.visualization.arrayToDataTable([
			['Label', 'Value'],
  			['SPO2', <?php echo $value_spo2_data; ?>]]);

		var avg_bpm_data = google.visualization.arrayToDataTable([
			['Label', 'Value'],
  			['AVG BPM', <?php echo $chart_avg_bpm_data; ?>]]);

		var avg_spo2_data = google.visualization.arrayToDataTable([
			['Label', 'Value'],
  			['AVG SPO2', <?php echo $chart_avg_spo2_data; ?>]]);

        var BPM_Chart_Options = {
          min: 0, max: 200,
          greenFrom: 45, greenTo: 130,
          redFrom: 160, redTo: 200,
          yellowFrom:130, yellowTo: 160,
          minorTicks: 5,
        };
        		    
        var SPO2_Chart_Options = {
          min: 0, max: 100,
          greenFrom: 92, greenTo: 100,
          redFrom: 50, redTo: 88,
          yellowFrom:88, yellowTo: 92,
          minorTicks: 5,
        };
        		    
        // Create and draw the visualization.
/*        var bpm_chart = new google.visualization.Gauge(document.getElementById('chart2'));
        bpm_chart.draw(bpm_data, BPM_Chart_Options);

        var spo2_chart = new google.visualization.Gauge(document.getElementById('chart1'));
        spo2_chart.draw(spo2_data, SPO2_Chart_Options);
*/
        var avg_bpm_chart = new google.visualization.Gauge(document.getElementById('chart4'));
        avg_bpm_chart.draw(avg_bpm_data, BPM_Chart_Options);

        var avg_spo2_chart = new google.visualization.Gauge(document.getElementById('chart3'));
        avg_spo2_chart.draw(avg_spo2_data, SPO2_Chart_Options);

/*
var data = new google.visualization.DataTable(); 
data.addColumn('timeofday', 'Time'); 
data.addColumn('number', 'BPM'); 
data.addColumn('number', 'SPO2'); 
data.addRows([ 
	[[20,44,01], 105, 97], 
	[[20,44,02], 104, 97], 
	[[20,44,03], 104, 97], 
	[[20,44,04], 103, 97], 
	[[20,44,05], 103, 97], 
	[[20,44,06], 105, 97]]);
*/





      <?php echo $data; ?>

      var options = {
        theme:'maximized',
	title: 'Last 20 minutes',
	width: 600,
	height: 350,
        hAxis: {
          title: 'Time'
        },
        vAxis: {
          title: 'BPM and SPO2'
        },
        colors: ['green', 'red'],
/*        trendlines: {
          0: {type: 'linear', color: '#333', opacity: 1},
          1: {type: 'linear', color: '#111', opacity: .3}
        }
*/      
	axisTitlesPosition: 'none',
	curveType: 'function',
	legend: {position: 'in'}
        };

/*
      var classicOptions = {
        title: 'BPM and SPO2 for the last hour',
        width: 300,
        height: 300,
        // Gives each series an axis that matches the vAxes number below.
        series: {
          0: {targetAxisIndex: 0},x
          1: {targetAxisIndex: 1}
        },
        vAxes: {
          // Adds titles to each axis.
          0: {title: 'BPM'},
          1: {title: 'SPO2'}
        },
        hAxis: {
//                 [new Date(2014, 0), new Date(2014, 1), new Date(2014, 2), new Date(2014, 3),
 //                 new Date(2014, 4),  new Date(2014, 5), new Date(2014, 6), new Date(2014, 7),
 //                 new Date(2014, 8), new Date(2014, 9), new Date(2014, 10), new Date(2014, 11)
 //                ]
        },
        vAxis: {
          viewWindow: {
            max: 200
          }
        }
      };

*/

        var lineChart = new google.visualization.LineChart(document.getElementById('lineChart_div'));
        lineChart.draw(data, options);
    }

    google.setOnLoadCallback(drawVisualization);
    </script>

  </head>
  <body style="font-family: 'Arial';border: 0 none;">
  <link href='https://fonts.googleapis.com/css?family=Quantico&text=0123456789-' rel='stylesheet' type='text/css'>
  <div id="containerDiv" style="width: 300px;">
    <div id="Error" style="float:left; text-align:center; width: 300px; " <?php echo ($hide) ? "display:none;>" :  ">ERROR: " . $value_exc_data . " - " . $exception_msg; ?></div>
    <div id="SPO2Label" style="float:left; text-align:center; width: 150px; height: 20px; font-size:100%; font:bold;">%SPO<sub>2</sub></div>
    <div id="BPMLabel" style="float:left; text-align:center; width: 150px; height: 20px; font-size:100%; font:bold;">BPM</div>
    <div id="SPO2" style="float:left; text-align:center; width: 140px; height:80px; line-height:80px; font-size:400%; color:red; font-family: 'Quantico', sans-serif; background-color:black; border:5px solid white; border-radius:15px;"><?php echo "$value_spo2_data"; ?></div>
    <div id="BPM" style="float:left; text-align:center; width: 140px; height:80px;  line-height:80px; font-size:400%; color:green; font-family: 'Quantico', sans-serif; background-color:black; border:5px solid white; border-radius:15px;"><?php echo "$value_bpm_data"; ?></div>
    <div id="lineChart_div" style="float:left; width: 600px; height: 350px; margin:0px;"></div>
<!--
    <div id="chart1" style="float:left; width: 150px; height: 150px;"></div>
    <div id="chart2" style="float:left; width: 150px; height: 150px;"></div>
-->
    <div id="chart3" style="float:left; width: 150px; height: 150px;"></div>
    <div id="chart4" style="float:left; width: 150px; height: 150px;"></div>
    <div id="updated" style="float:left; text-align:center; width: 300px;"><?php echo "Updated " . date("m/d/Y g:i:s a"); ?></div>
    <div id="disclaimer" style="float:left; text-align:center; width: 300px; font-size:50%; font:italic;">Averages are based on last 3600 readings (approximately 1 hour)</div>
  </div>
  </body>
</html>
