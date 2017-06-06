<?php

/*
 * MailWatch for MailScanner
 * Copyright (C) 2003-2011  Steve Freegard (steve@freegard.name)
 * Copyright (C) 2011  Garrod Alwood (garrod.alwood@lorodoes.com)
 * Copyright (C) 2014-2017  MailWatch Team (https://github.com/mailwatch/1.2.0/graphs/contributors)
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
 * As a special exception, you have permission to link this program with the JpGraph library and distribute executables,
 * as long as you follow the requirements of the GNU GPL in regard to all of the software in the executable aside from
 * JpGraph.
 *
 * You should have received a copy of the GNU General Public License along with this program; if not, write to the Free
 * Software Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 */

class GraphGenerator
{
    public $sqlQuery;
    public $graphTitle;
    public $sqlColumns = array();
    public $tableColumns = array();
    public $graphColumns = array();
    public $valueConversion = array();
    public $types = null;
    public $printTable = true;

    private $data;
    private $numResult;


    public function __construct()
    {
    }

    /**
     * @return void
     */
    public function printPieGraph()
    {
        $this->prepareData();

        $this->runConversions();
        if (count($this->data[$this->graphColumns['dataNumericColumn']]) === 0) {
            echo __('nodata03');
            return;
        }

        //create canvas graph
        echo '<canvas id="reportChart" class="reportGraph"></canvas>
      <script>
      var COLON = "' . __('colon99') . '";
      var chartTitle = "' . $this->graphTitle . '";
      var chartId = "reportChart";
      var chartLabels = ["' . implode('", "', $this->data[$this->graphColumns['labelColumn']]) . '"];
      var chartNumericData = [' . implode(', ', $this->data[$this->graphColumns['dataNumericColumn']]) . '];
      var chartFormattedData = ["' . implode('", "', $this->data[$this->graphColumns['dataFormattedColumn']]) . '"];
      </script>
      <script src="lib/Chart.js/Chart.min.js"></script>
      <script src="lib/pieConfig.js"></script>';

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
        $this->prepareData();

        $this->runConversions();

        $numericData = "";
        $formattedData = "";
        $dataLabels="";
        $graphTypes="";

        for ($i=0; $i<count($this->graphColumns['dataNumericColumns']); $i++) {
            //foreach yaxis get the column name for numeric and formatted data
            $numericData .= '[' . "\n";
            $formattedData .= '[' . "\n";
            $dataLabels .= '[' . "\n";
            $graphTypes .= '[' . "\n";
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
        echo '<canvas id="reportChart" class="lineGraph"></canvas>
      <script>
      var COLON = "' . __('colon99') . '";
      var chartTitle = "' . $this->graphTitle . '";
      var chartId = "reportChart";
      var chartLabels = ["' . implode('", "', $this->data[$this->graphColumns['labelColumn']]) . '"];
      var chartNumericData = [' . $numericData . '];
      var chartFormattedData = [' . $formattedData . '];
      var xAxeDescription = "' . $this->graphColumns['xAxeDescription'] . '";
      var yAxeDescriptions = ["' . implode('", "', $this->graphColumns['yAxeDescriptions']) . '"];
      var fillBelowLine = [' . implode(', ', $this->graphColumns['fillBelowLine']) . '];
      ' . (isset($this->graphColumns['dataLabels']) ? 'var chartDataLabels = [' . $dataLabels . '];' : '') . '
      ' . ($graphTypes === null ? '' : 'var types = [' .  $graphTypes . ']') . '
      </script>
      <script src="lib/Chart.js/Chart.js"></script>
      <script src="lib/lineConfig.js"></script>';

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
                case 'generatehours':
                    $this->generateHours();
                    break;
                case 'assignperhour':
                    $this->convertAssignPerHour($column);
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
     * @return void
     */
    protected function prepareData()
    {
        $result = dbquery($this->sqlQuery);
        $this->data = array();
        $this->numResult = $result->num_rows;
        if ($this->numResult <= 0) {
            die(__('diemysql99') . "\n");
        }
        //store data in format $data[columnname][rowid]
        while ($row = $result->fetch_assoc()) {
            foreach ($this->sqlColumns as $columnName) {
                $this->data[$columnName][] = $row[$columnName];
            }
        }
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
        $this->data['hostname'] = array();
        $this->data['geoip'] = array();
        foreach ($this->data[$column] as $ipval) {
            $hostname = gethostbyaddr($ipval);
            if ($hostname === $ipval) {
                $this->data['hostname'][] = __('hostfailed39');
            } else {
                $this->data['hostname'][] = $hostname;
            }
            if ($geoip = return_geoip_country($ipval)) {
                $this->data['geoip'][] = $geoip;
            } else {
                $this->data['geoip'][] = __('geoipfailed39');
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
        $viruses = array();
        foreach ($this->data[$column] as $report) {
            if (preg_match(VIRUS_REGEX, $report, $virus_report)) {
                $virus = $virus_report[2];
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
        $this->data['virusname'] = array();
        $this->data['viruscount'] = array();
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
     * Generates $this->data['hours'] with the last 25 hours beginning with the oldest one
     *
     * @return void
     */
    protected function generateHours()
    {
        $current = new DateTime();
        $date = $current->sub(new DateInterval("P1DT1H"));
        $dates = array();
        for ($i=0;$i< 25;$i++) {
            $date = $date->add(new DateInterval("PT1H"));
            $hour = $date->format("H");
            $dates[] = $hour . ':00-' . (intval($hour)+1) . ':00';
        }
        $this->data['hours'] = $dates;
        $this->numResult = 25;
    }

    /**
     * Converts the data from $this->data[$column] so that it is mapped to an hour
     *
     * @param string $column the data column that shall be converted
     * @return void
     */
    protected function convertAssignPerHour($column)
    {
        $convertedData = array();
        for ($i=0; $i< 25; $i++) {
            $convertedData[] = 0;
        }
        $start = (new DateTime())->sub(new DateInterval("P1D"));
        $count = count($this->data['xaxis']);
        for ($i=0; $i<$count; $i++) {
            // get the value from data and add it to the corresponding hour
            $timeDiff = $start->diff((new DateTime($this->data['xaxis'][$i])), true);
            $convertedData[$timeDiff->format('%h')] += $this->data[$column][$i];
        }
        $this->data[$column . 'conv'] = $convertedData;
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
