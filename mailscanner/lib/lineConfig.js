/**
 * This customizes a line chart for ChartJs. This files requires that the variables chartTitle, chartId, chartFormattedData and chartNumericData, fillBelowLine, COLON are already set.
 */

/******modify the colors here****/
var mailColor   = '#4973f7'; // blue
var virusColor  = '#B22222'; // dark red
var spamColor   = '#EE6262'; // red
var volumeColor = '#f5d932'; // yellow
var mcpColor    = '#b9e3f9'; // light blue
/*******************************/

var defaultColors= [
  mailColor,
  virusColor,
  spamColor,
  volumeColor,
  mcpColor
];

function getColor(axisid, lineid, datasetid, customColors) {
  if (typeof customColors !== 'undefined') {
    var color = customColors[axisid][lineid];
    if (typeof color !== 'undefined' && typeof window[color] !== 'undefined') {
      return window[color];
    }
  }
  return defaultColors[datasetid];
}

// see https://stackoverflow.com/questions/15900485/correct-way-to-convert-size-in-bytes-to-kb-mb-gb-in-javascript
function formatBytes(a,i,v){if(0==a)return"0B";var c=1e3,e=["Bytes","KB","MB","GB","TB","PB","EB","ZB","YB"],f=Math.floor(Math.log(a)/Math.log(c));return parseFloat((a/Math.pow(c,f)).toFixed(0))+" "+e[f]}

function findBestTickCount(valueCount, minCount, maxCount) {
  var bestMatch = Number.MAX_VALUE;
  var bestValue = minCount;
  for(i=Math.ceil(valueCount/maxCount); i<= Math.floor(valueCount/minCount);i++) {
    var val = valueCount/i;
    var diff = Math.abs(Math.round(val)-val);
    if(diff < bestMatch) {
      bestMatch = diff;
      bestValue = i;
    }
  }
  return {val: bestValue, match: bestMatch};
}

function autoSkipTick(value, index, valueCount, bestTick, gridFactor) {
  //second condition is to prevent ticks close to the end overlapping
  if((index % bestTick.val <= bestTick.match && valueCount-bestTick.val-index >= 0)|| index == valueCount-1) {
  console.log(index);
    //label and grid line
    return value;
  } else {
    if(bestTick.val/gridFactor == Math.round(bestTick.val/gridFactor) && (index % Math.ceil(bestTick.val/gridFactor) <= bestTick.match || index == valueCount-1)) {
      //no label but grid lines
      return "";
    } else {
      //no label, no grid line
      return;
    }
  }
}

function getBestGridFactor(bestTick, valueCount, maxGridCount) {
  var gridFactor =1;
  for(; gridFactor<=bestTick.val && gridFactor * valueCount/bestTick.val <= maxGridCount; gridFactor*=2) {
    if(bestTick.val/gridFactor % 2 != 0)  {
      break;
    }
  }
  return gridFactor;
}

function printLineGraph(chartId, settings) {
  var ctx = document.getElementById(chartId);
  var bestTick = findBestTickCount(settings.chartLabels.length-1, 2, settings.maxTicks);
  var bestGridFactor = getBestGridFactor(bestTick, settings.chartLabels.length - 1, settings.maxTicks * 3);
  var myChart = new Chart(ctx, {
    type: "bar",
    data: {
      labels: settings.chartLabels,
      datasets: (function() {
        datasetsTmp=[];
        for(i=0;i<settings.chartNumericData.length;i++) {
          //each yaxe
          for(j=0;j<settings.chartNumericData[i].length;j++) {
            datasetsTmp.push({
              label: (typeof settings.chartDataLabels !== 'undefined' ? settings.chartDataLabels[i][j] : ''),
              data: settings.chartNumericData[i][j],
              backgroundColor: getColor(i, j, datasetsTmp.length, settings.colors),
              borderColor: getColor(i, j, datasetsTmp.length, settings.colors),
              fill: settings.fillBelowLine[i],
              yAxisID: "y-axis-"+i,
              type: (typeof settings.types !== 'undefined' ? settings.types[i][j] : "line"),
              showLine: (typeof settings.types === 'undefined' || settings.fillBelowLine[i] ? true :
                          (typeof settings.drawLines === 'undefined' ? false : settings.drawLines)),
              pointRadius: 0,
              borderWidth: 1.5          
            });
          }
        }
        return datasetsTmp;
      })()
    },
    options: {
      title: {
        display: (typeof settings.plainGraph === 'undefined' ? true : !settings.plainGraph),
        fontSize: 18,
        text: settings.chartTitle
      },
      legend: {
        display: (typeof settings.chartDataLabels === 'undefined' ? false : true),
      },
      elements: {
        line: {
          tension: 0, // disables bezier curves
        }
      },
      scales: {
        yAxes: (function() {
          axes = [];
          for(i=0;i<settings.yAxeDescriptions.length;i++) {
            //get max for all of yaxis to set the axis max
            var max = 0;
            for(j=0;j<settings.chartFormattedData[i].length;j++) {
              max = Math.max(
                max,
                Math.max.apply(null, settings.chartNumericData[i][j])
              );
            }
            axes.push({
              id: "y-axis-"+i,
              position: (i%2 == 0 ? "left" : "right" ),
              scaleLabel: {
                display: (typeof settings.plainGraph === 'undefined' ? true : !settings.plainGraph),
                labelString: settings.yAxeDescriptions[i]
              },
              ticks: {
                suggestedMax: max * 1.05,
                min: 0,
                callback: ((typeof settings.valueTypes === 'undefined' || settings.valueTypes[i] == 'plain') ? 
                            Chart.Ticks.formatters.linear :
                            formatBytes
                          
                )
              },
            });
          }
          return axes;
        })(),
        xAxes: [{
          maxBarThickness: 7,
          gridLines: {offsetGridLines: false},
          scaleLabel: {
            display: (typeof settings.plainGraph === 'undefined' ? true : !settings.plainGraph),
            labelString: settings.xAxeDescription,
          },
          ticks: {
                callback: function(tick, index, values) { 
                  return autoSkipTick(tick, index, values.length, bestTick, bestGridFactor)
                },
                stepSize: 1,
                autoSkip: false,
                maxRotation: 0,
          },
          type: 'category',
        }]
      },
      responsive: false,
      tooltips: {
        callbacks: {
          label: function(tooltipItem, data) {
            var dataset = data.datasets[tooltipItem.datasetIndex];
            var tooltipLabel = data.labels[tooltipItem.index];
            var itemData = dataset.data[tooltipItem.index];
            var total = 0;
            for (var i in dataset.data) {
              if (dataset._meta[0].data[i].hidden === false) {
                total += dataset.data[i];
              }
            }
            var tooltipPercentage = Math.round((itemData / total) * 100);
            // get id of y-axis to get the corresponding label
            var axisId = dataset.yAxisID.replace("y-axis-","");
            var count = 0;
            var formattedData = null;
            for (i=0;i<settings.chartFormattedData.length && formattedData == null;i++) {
              for (j=0;j<settings.chartFormattedData[i].length && formattedData == null; j++) {
                if (count == tooltipItem.datasetIndex) {
                  formattedData = settings.chartFormattedData[i][j][tooltipItem.index];
                } else {
                  count++;
                }
              }
            }
            //COLON specified on main page via php __('colon99')
            var tooltipOutput = " " + settings.yAxeDescriptions[axisId] + COLON + " " + formattedData;

            return tooltipOutput;
          }
        }
      },
      hover: { animationDuration: 0 }
    }
  });
}
