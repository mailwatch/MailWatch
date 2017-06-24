/**
 * This customizes a line chart for ChartJs. This files requires that the variables chartTitle, chartId, chartFormattedData and chartNumericData, fillBelowLine, COLON are already set.
 */
var lineColors= [
  '#61a9f3', //blue
  '#f381b9', //red
  '#61E3A9', //green
  '#fff3ad',
  '#b9e3f9'
];

// see https://stackoverflow.com/questions/15900485/correct-way-to-convert-size-in-bytes-to-kb-mb-gb-in-javascript
function formatBytes(a,i,v){if(0==a)return"0B";var c=1e3,e=["Bytes","KB","MB","GB","TB","PB","EB","ZB","YB"],f=Math.floor(Math.log(a)/Math.log(c));return parseFloat((a/Math.pow(c,f)).toFixed(0))+" "+e[f]}

function printLineGraph(chartId, settings) {
  var ctx = document.getElementById(chartId);
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
              backgroundColor: lineColors[datasetsTmp.length],
              borderColor: lineColors[datasetsTmp.length],
              fill: false,
              yAxisID: "y-axis-"+i,
              type: (typeof settings.types !== 'undefined' ? settings.types[i][j] : "line"),
              showLine: (typeof settings.types === 'undefined' || settings.fillBelowLine[i] ? true :
                          (typeof settings.drawLines === 'undefined' ? false : settings.drawLines)),
              pointRadius: 1,
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
          maxBarThickness: 3,
//        gridLines: {offsetGridLines: false},
          scaleLabel: {
            display: (typeof settings.plainGraph === 'undefined' ? true : !settings.plainGraph),
            labelString: settings.xAxeDescription,
          },
          time: {
            unit: 'hour',
            displayFormats: {
              'minute': 'HH:mm',
              'hour': 'HH:mm',
              max: (new Date()).toISOString(),
              min: (function(){
                var date = new Date();
                date.setDate(date.getDate()-1);
                return date.toISOString();
              })()
            }
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
