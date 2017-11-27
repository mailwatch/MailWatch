/**
 * This customizes a pie chart for ChartJs. This files requires that the variables chartTitle, chartId, chartFormattedData and chartNumericData, COLON are already set.
 */

var pieBackgroundColors= [
  '#61a9f3', //blue
  '#f381b9', //red
  '#61E3A9', //green
  //'#D56DE2',
  '#85eD82',
  '#F7b7b7',
  '#CFDF49',
  '#88d8f2',
  '#07AF7B',
  '#B9E3F9',
  '#FFF3AD',
  '#EF606A',
  '#EC8833',
  '#FFF100',
  '#87C9A5'
];

function drawPersistentPercentValues() {
  var ctx = this.chart.ctx;
  ctx.font = Chart.helpers.fontString(Chart.defaults.global.defaultFontSize, "normal", Chart.defaults.global.defaultFontFamily);
  ctx.fillStyle = this.chart.config.options.defaultFontColor;
  this.data.datasets.forEach(function (dataset) {
    var sum =0;
    for (var i = 0; i < dataset.data.length; i++) {
      if(dataset.hidden === true || dataset._meta[0].data[i].hidden === true){ continue; }
      sum += dataset.data[i];
    }
    var curr= 0;
    for (var i = 0; i < dataset.data.length; i++) {
      if(dataset.hidden === true || dataset._meta[0].data[i].hidden === true){ continue; }
      var part = dataset.data[i]/sum;
      if(dataset.data[i] !== null && part*100 > 2) {
        var model = dataset._meta[Object.keys(dataset._meta)[0]].data[i]._model;
        var radius = model.outerRadius-10; //where to place the text around the center
        var x = Math.sin((curr+part/2)*2*Math.PI)*radius;
        var y = Math.cos((curr+part/2)*2*Math.PI)*radius;
        ctx.fillText((part<0.1?" ":"")+(part*100).toFixed(0)+"%",model.x + x * 0.95 - 15, model.y - y * 0.96 - 8);
        curr += part;
      }
    }
  });
}

function getChartBgColors(count) {
  var bgColors = [];
  for (var i=0; bgColors.length < count; i++) {
    bgColors.push(pieBackgroundColors[i]);
  }
  return bgColors;
}

function printPieGraph(chartId, settings) {
  var ctx = document.getElementById(chartId);
  var myChart = new Chart(ctx, {
    type: "pie",
    data: {
      labels: settings.chartLabels,
      datasets: [{
        label: settings.chartTitle,
        data: settings.chartNumericData,
        backgroundColor: getChartBgColors(settings.chartNumericData.length)
      }]
    },
    options: {
      title: {
        display: true,
        fontSize: 18,
        text: settings.chartTitle
      },
      legend: {
        display: true,
        labels: {
          generateLabels: function(graph) {
            var defaultLabels = Chart.defaults.doughnut.legend.labels.generateLabels(graph);
            /* uncomment this to additionally add the percentage to the labels
            var graphData = graph.data.datasets[0].data;
            var total = 0;
            for(var i=0; i<graphData.length; i++) {
              total += graphData[i];
            };
            for(var i=0; i<defaultLabels.length; i++) {
              var label = defaultLabels[i];
              var percentage = Math.round((graphData[i] / total) * 100);
              defaultLabels[i].text += " (" + percentage +"%)";
            }*/
            return defaultLabels;
          }
        }
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
            //COLON specified on main page via php __('colon99')
            var tooltipOutput = " " + tooltipLabel + COLON + " " + settings.chartFormattedData[tooltipItem.index];
            if (tooltipPercentage < 3) {
              tooltipOutput += " (" + tooltipPercentage + "%)";
            }
            return tooltipOutput;
          }
        }
      },
      animation: {
        onProgress: drawPersistentPercentValues,
        onComplete: drawPersistentPercentValues
      },
      hover: { animationDuration: 0 }
    }
  });
}
