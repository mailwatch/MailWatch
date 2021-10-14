<?php

/*
 * MailWatch for MailScanner
 * Copyright (C) 2003-2011  Steve Freegard (steve@freegard.name)
 * Copyright (C) 2011  Garrod Alwood (garrod.alwood@lorodoes.com)
 * Copyright (C) 2014-2018  MailWatch Team (https://github.com/mailwatch/1.2.0/graphs/contributors)
 *
 * This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public
 * License as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later
 * version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU General Public License for more details.
 *
 * In addition, as a special exception, the copyright holder gives permission to link the code of this program with
 * those files in the PEAR library that are licensed under the PHP License (or with modified versions of those files
 * that use the same license as those files), and distribute linked combinations including the two.
 * You must obey the GNU General Public License in all respects for all of the code used other than those files in the
 * PEAR library that are licensed under the PHP License. If you modify this program, you may extend this exception to
 * your version of the program, but you are not obligated to do so.
 * If you do not wish to do so, delete this exception statement from your version.
 *
 * You should have received a copy of the GNU General Public License along with this program; if not, write to the Free
 * Software Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 */

class GraphGenerator
{
    public $sqlQuery;
    public $graphTitle;
    public $sqlColumns = [];
    public $tableColumns = [];
    public $graphColumns = [];
    public $valueConversion = [];
    public $types = null;
    public $printTable = true;
    public $settings = [];

    private $data = [];
    private $numResult;


    public function __construct()
    {
    }

    /**
     * @return void
     */
    public function printPieGraph()
    {
        if ($this->prepareData() === false) {
            return;
        }
        $this->runConversions();
        if (count($this->data[$this->graphColumns['dataNumericColumn']]) === 0) {
            echo __('nodata64');
            return;
        }

        $chartId = (isset($this->settings['chartId']) ? $this->settings['chartId'] : 'reportGraph');
        //create canvas graph
        echo '<canvas id="' . $chartId . '" class="reportGraph"></canvas>
      <script src="js/Chart.js/Chart.min.js"></script>
      <script src="js/pieConfig.js"></script>
      <script>
        COLON = "' . __('colon99') . '";
        printPieGraph("' . $chartId .'", {
          chartTitle :  "' . $this->graphTitle . '",
          chartId : "' . $chartId . '",
          chartLabels : ["' . implode('", "', $this->data[$this->graphColumns['labelColumn']]) . '"],
          chartNumericData : [' . implode(', ', $this->data[$this->graphColumns['dataNumericColumn']]) . '],
          chartFormattedData : ["' . implode('", "', $this->data[$this->graphColumns['dataFormattedColumn']]) . '"],
        });
      </script>
      <br>';

        $this->printTable();
    }

    /**
     * Generates a line/bar graph
     *
     * @return void
     */
    public function printLineGraph()
    {
        if ($this->types === null) {
            echo("No types defined");
            return;
        }

        if ($this->prepareData() === false) {
            return;
        }

        $this->runConversions();

        $numericData = "";
        $formattedData = "";
        $dataLabels="";
        $graphTypes="";
        $colors="";

        for ($i=0; $i<count($this->graphColumns['dataNumericColumns']); $i++) {
            //foreach yaxis get the column name for numeric and formatted data
            $numericData .= '[' . "\n";
            $formattedData .= '[' . "\n";
            $dataLabels .= '[' . "\n";
            $graphTypes .= '[' . "\n";
            $colors .= isset($this->settings['colors']) ? '["' . implode('", "', $this->settings['colors'][$i]) . '"],' : '';
            for ($j=0; $j<count($this->graphColumns['dataNumericColumns'][$i]); $j++) {
                if (isset($this->graphColumns['dataLabels'][$i])) {
                    $dataLabels .= '"' . $this->graphColumns['dataLabels'][$i][$j] .'",';
                }
                $graphTypes .= '"' . $this->types[$i][$j] . '",';
                $numericDataName = $this->graphColumns['dataNumericColumns'][$i][$j];
                $formattedDataName = $this->graphColumns['dataFormattedColumns'][$i][$j];
                $numericData .= '[' . implode(', ', $this->data[$numericDataName]) . '],';
                $formattedData .= '["' . implode('", "', $this->data[$formattedDataName]) . '"],';
            }
            $numericData .= '],' . "\n";
            $formattedData .= '],' . "\n";
            $dataLabels .= '],' . "\n";
            $graphTypes .= '],' . "\n";
        }
        $chartId = (isset($this->settings['chartId']) ? $this->settings['chartId'] : 'reportGraph');
        echo '<canvas id="' . $chartId . '" class="lineGraph"></canvas>
      <script src="js/Chart.js/Chart.bundle.min.js"></script>
      <script src="js/lineConfig.js"></script>
      <script>
        COLON = "' . __('colon99') . '";
        printLineGraph("' . $chartId . '",  {
          chartTitle : "' . $this->graphTitle . '",
          chartId : "' . $chartId . '",
          chartLabels : ["' . implode('", "', $this->data[$this->graphColumns['labelColumn']]) . '"],
          chartNumericData : [' . $numericData . '],
          chartFormattedData : [' . $formattedData . '],
          xAxeDescription : "' . $this->graphColumns['xAxeDescription'] . '",
          yAxeDescriptions : ["' . implode('", "', $this->graphColumns['yAxeDescriptions']) . '"],
          fillBelowLine : [' . implode(', ', $this->graphColumns['fillBelowLine']) . '],
          plainGraph : ' . (isset($this->settings['plainGraph']) && $this->settings['plainGraph'] === true ?  'true' : 'false'). ',
          maxTicks: ' . (isset($this->settings['maxTicks']) ? $this->settings['maxTicks'] : '12') . ',
          ' . (isset($this->settings['drawLines']) && $this->settings['drawLines'] === true ?  'drawLines : true,' : ''). '
          ' . (isset($this->settings['colors']) ? 'colors : [' . $colors . '],'  : ''). '
          ' . (isset($this->settings['valueTypes']) && count($this->settings['valueTypes']) !== 0 ?  'valueTypes: ["' . implode('","', $this->settings['valueTypes']) . '"],' : ''). '
          ' . (isset($this->graphColumns['dataLabels']) ? 'chartDataLabels : [' . $dataLabels . '],' : '') . '
          ' . ($graphTypes === null ? '' : 'types : [' .  $graphTypes . '],') . '
        });
      </script>';

        $this->printTable();
    }

    /**
     * Executes the conversion defined by $this->valueConversion
     *
     * @return void
     */
    protected function runConversions()
    {
        foreach ($this->valueConversion as $column => $conversion) {
            switch ($conversion) {
                case 'scale':
                    $this->convertScale($column);
                    break;
                case 'number':
                    $this->convertNumber($column);
                    break;
                case 'generatetimescale':
                    $this->generateTimeScale();
                    break;
                case 'timescale':
                    $this->convertToTimeScale($column);
                    break;
                case 'hostnamegeoip':
                    $this->convertHostnameGeoip($column);
                    break;
                case 'countviruses':
                    $this->convertViruses($column);
                    break;
            }
        }
    }

    /**
     * Gets the data for $this->sqlQuery from db and stores it in $this->data
     *
     * @return boolean true on success, false on error
     */
    protected function prepareData()
    {
        $result = dbquery($this->sqlQuery);
        $this->data = [];
        $this->numResult = $result->num_rows;
        if ($this->numResult <= 0 && (!isset($this->settings['ignoreEmptyResult']) || $this->settings['ignoreEmptyResult'] === false)) {
            echo __('diemysql99') . "\n";
            return false;
        }
        //store data in format $data[columnname][rowid]
        while ($row = $result->fetch_assoc()) {
            foreach ($this->sqlColumns as $columnName) {
                $this->data[$columnName][] = $row[$columnName];
            }
        }
        return true;
    }

    /**
     * Converts the data from $this->data[$column] to numbers
     *
     * @param string $column the data column that shall be converted
     * @return void
     */
    protected function convertNumber($column)
    {
        $this->data[$column . 'conv'] = array_map(
            function ($val) {
                return number_format($val);
            },
            $this->data[$column]
        );
    }

    /**
     * Converts the data from $this->data[$column] so that so that it is scaled in kB, MB, GB etc
     *
     * @param string $column the data column that shall be converted
     * @return void
     */
    protected function convertScale($column)
    {
        // Work out best size
        $this->data[$column . 'conv'] = $this->data[$column];
        format_report_volume($this->data[$column . 'conv'], $size_info);
        $scale = $size_info['formula'];
        foreach ($this->data[$column . 'conv'] as $key => $val) {
            $this->data[$column . 'conv'][$key] = formatSize($val * $scale);
        }
    }

    /**
     * Converts the data (ip address) from $this->data[$column] so that the hostname and geoip lookup are generated in $this->data['hostname'] and $this->data['geoip']
     *
     * @param string $column the data column that shall be converted
     * @return void
     */
    protected function convertHostnameGeoip($column)
    {
        $this->data['hostname'] = [];
        $this->data['geoip'] = [];
        foreach ($this->data[$column] as $ipval) {
            $hostname = gethostbyaddr($ipval);
            if ($hostname === $ipval) {
                $this->data['hostname'][] = __('hostfailed64');
            } else {
                $this->data['hostname'][] = $hostname;
            }
            if ($geoip = return_geoip_country($ipval)) {
                $this->data['geoip'][] = $geoip;
            } else {
                $this->data['geoip'][] = __('geoipfailed64');
            }
        }
    }

    /**
     * Converts the data from $this->data[$column] so that virus names and counter are inserted in $this->data['virusname'] and $this->data['viruscount']
     *
     * @param string $column the data column that shall be converted
     * @return void
     */
    protected function convertViruses($column)
    {
        $viruses = [];
        foreach ($this->data[$column] as $report) {
            $virus = getVirus($report);
            if ($virus !== null) {
                if (isset($viruses[$virus])) {
                    $viruses[$virus]++;
                } else {
                    $viruses[$virus] = 1;
                }
            }
        }
        arsort($viruses);
        reset($viruses);
        $count = 0;
        $this->data['virusname'] = [];
        $this->data['viruscount'] = [];
        foreach ($viruses as $key => $val) {
            $this->data['virusname'][] = $key;
            $this->data['viruscount'][] = $val;
            if (++$count >= 10) {
                break;
            }
        }
        $this->numResult = $count;
    }

    /**
     * Generates $this->data['time'] with the time beginning with $this->settings['timeInterval'] and
     * in steps of $this->settings['timeScale']
     *
     * @return void
     */
    protected function generateTimeScale()
    {
        if (!isset($this->settings['timeInterval']) || !isset($this->settings['timeScale'])
             || !isset($this->settings['timeFormat'])) {
            throw new \Exception('timeInterval or timeScale not set');
        }
        $interval = $this->settings['timeInterval'];
        $scale = $this->settings['timeScale'];
        $format = str_replace('%', '', $this->settings['timeFormat']);

        $now = new DateTime();
        $this->settings['now'] = $now;
        $date = clone $now;
        $date = $date->sub(new DateInterval($interval));
        $dates = [$date->format($format)];
        $count = 1;
        while ($date < $now) {
            //get the next interval and create the label for it
            $date = $date->add(new DateInterval($scale));
            $dates[] = $date->format($format);
            $count++;
        }
        //store the time scales and define the result count
        $this->data['time'] = $dates;
        $this->numResult = $count;
    }

    /**
     * Converts the data from $this->data[$column] so that it is mapped to an time scale
     *
     * @param string $column the data column that shall be converted
     * @return void
     */
    protected function convertToTimeScale($column)
    {
        if (!isset($this->settings['timeInterval'], $this->settings['timeScale'], $this->settings['timeFormat'])) {
            throw new \Exception('timeInterval or timeScale not set');
        }
        $interval = $this->settings['timeInterval'];
        $scale = $this->settings['timeScale'];
        $format = $this->settings['timeGroupFormat'];

        $now = $this->settings['now'];
        $start = clone $now;
        $start = $start->sub(new DateInterval($interval));
        $oldest = clone $start;
        //initialize the time scales with zeros
        $convertedData = [($start->format($format)) => 0];
        while ($start < $now) {
            $convertedData[$start->add(new DateInterval($scale))->format($format)] = 0;
        }
        //get the values from the sql result and assign them to the correct time scale part
        $count = isset($this->data['xaxis']) ? count($this->data['xaxis']) : 0;
        for ($i=0; $i<$count; $i++) {
            // get the value from data and add it to the corresponding hour
            $time = new DateTime($this->data['xaxis'][$i]);
            //recheck if the entry is inside the value range
            if ($time >= $oldest && $time < $now) {
                $convertedData[$time->format($format)] += $this->data[$column][$i];
            }
        }
        //we only need the value and not the keys
        $this->data[$column . 'conv'] = array_values($convertedData);
    }

    /**
     * Prints a html table with the columns defined by $this->tableColumns using the data in $this->data if $this->printTable is true
     *
     * @return void
     */
    public function printTable()
    {
        if ($this->printTable !== true) {
            return;
        }
        // HTML to display the table
        echo '<table class="reportTable">';
        echo '    <tr>' . "\n";
        foreach ($this->tableColumns as $columnName => $columnTitle) {
            echo '     <th>' . $columnTitle . '</th>' . "\n";
        }
        echo '    </tr>' . "\n";

        for ($i = 0; $i < $this->numResult; $i++) {
            echo '    <tr>' . "\n";
            foreach ($this->tableColumns as $columnName => $columnTitle) {
                echo '     <td>' . $this->data[$columnName][$i] . '</td>' . "\n";
            }
            echo '    </tr>' . "\n";
        }
        echo '   </table>' . "\n";
    }
}
