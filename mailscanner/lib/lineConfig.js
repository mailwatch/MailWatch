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

// See https://stackoverflow.com/questions/37250456/chart-js-evenly-distribute-ticks-when-using-maxtickslimit/37257056#37257056
Chart.pluginService.register({
	afterUpdate: function (chart) {
		var xScale = chart.scales['x-axis-0'];
		if (xScale.options.ticks.maxTicksLimit) {
			// store the original maxTicksLimit
			xScale.options.ticks._maxTicksLimit = xScale.options.ticks.maxTicksLimit;
			// let chart.js draw the first and last label
			xScale.options.ticks.maxTicksLimit = (xScale.ticks.length % xScale.options.ticks._maxTicksLimit === 0) ? 1 : 2;

			var originalXScaleDraw = xScale.draw
			xScale.draw = function () {
				originalXScaleDraw.apply(this, arguments);

				var xScale = chart.scales['x-axis-0'];
				if (xScale.options.ticks.maxTicksLimit) {
					var helpers = Chart.helpers;

					var tickFontColor = helpers.getValueOrDefault(xScale.options.ticks.fontColor, Chart.defaults.global.defaultFontColor);
					var tickFontSize = helpers.getValueOrDefault(xScale.options.ticks.fontSize, Chart.defaults.global.defaultFontSize);
					var tickFontStyle = helpers.getValueOrDefault(xScale.options.ticks.fontStyle, Chart.defaults.global.defaultFontStyle);
					var tickFontFamily = helpers.getValueOrDefault(xScale.options.ticks.fontFamily, Chart.defaults.global.defaultFontFamily);
					var tickLabelFont = helpers.fontString(tickFontSize, tickFontStyle, tickFontFamily);
					var tl = xScale.options.gridLines.tickMarkLength;

					var isRotated = xScale.labelRotation !== 0;
					var yTickStart = xScale.top;
					var yTickEnd = xScale.top + tl;
					var chartArea = chart.chartArea;

					// use the saved ticks
					var maxTicks = xScale.options.ticks._maxTicksLimit - 1;
					var ticksPerVisibleTick = xScale.ticks.length / maxTicks;

					// chart.js uses an integral skipRatio - this causes all the fractional ticks to be accounted for between the last 2 labels
					// we use a fractional skipRatio
					var ticksCovered = 0;
					helpers.each(xScale.ticks, function (label, index) {
						if (index < ticksCovered)
							return;

						ticksCovered += ticksPerVisibleTick;

						// chart.js has already drawn these 2
						if (index === 0 || index === (xScale.ticks.length - 1))
							return;

						// copy of chart.js code
						var xLineValue = this.getPixelForTick(index);
						var xLabelValue = this.getPixelForTick(index, this.options.gridLines.offsetGridLines);

						if (this.options.gridLines.display) {
							this.ctx.lineWidth = this.options.gridLines.lineWidth;
							this.ctx.strokeStyle = this.options.gridLines.color;

							xLineValue += helpers.aliasPixel(this.ctx.lineWidth);

							// Draw the label area
							this.ctx.beginPath();

							if (this.options.gridLines.drawTicks) {
								this.ctx.moveTo(xLineValue, yTickStart);
								this.ctx.lineTo(xLineValue, yTickEnd);
							}

							// Draw the chart area
							if (this.options.gridLines.drawOnChartArea) {
								this.ctx.moveTo(xLineValue, chartArea.top);
								this.ctx.lineTo(xLineValue, chartArea.bottom);
							}

							// Need to stroke in the loop because we are potentially changing line widths & colours
							this.ctx.stroke();
						}

						if (this.options.ticks.display) {
							this.ctx.save();
							this.ctx.translate(xLabelValue + this.options.ticks.labelOffset, (isRotated) ? this.top + 12 : this.options.position === "top" ? this.bottom - tl : this.top + tl);
							this.ctx.rotate(helpers.toRadians(this.labelRotation) * -1);
							this.ctx.font = tickLabelFont;
							this.ctx.fillStyle = tickFontColor;
							this.ctx.textAlign = (isRotated) ? "right" : "center";
							this.ctx.textBaseline = (isRotated) ? "middle" : this.options.position === "top" ? "bottom" : "top";
							this.ctx.fillText(label, 0, 0);
							this.ctx.restore();
						}
					}, xScale);
				}
			};
		}
	},
});

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
              backgroundColor: getColor(i, j, datasetsTmp.length, settings.colors),
              borderColor: getColor(i, j, datasetsTmp.length, settings.colors),
              fill: settings.fillBelowLine[i],
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
        // For Padding look at https://stackoverflow.com/questions/42585861/chart-js-increase-spacing-between-legend-and-chart
        // or https://stackoverflow.com/questions/42870869/chartjs-top-and-bottom-padding-of-a-chart-area
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
          ticks: {
              // Need to be a variable (for rep_total_mail_by_date.php and rep_previous_day.php)
              // auto or a number, curenltu not good in 2 rep_ files
              maxTicksLimit: 10
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
            // COLON specified on main page via php __('colon99')
            var tooltipOutput = " " + settings.yAxeDescriptions[axisId] + COLON + " " + formattedData;

            return tooltipOutput;
          }
        }
      },
      hover: { animationDuration: 0 }
    }
  });
}