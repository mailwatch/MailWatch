/**
 * This customizes a pie chart for ChartJs. This files requires that the variables chartTitle, chartId and chartData are already set.
 */
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
  var styleInfo = getComputedStyle(document.documentElement);
  var bgColors = [];
  for (var i=1; bgColors.length < count; i++) {
    var color = styleInfo.getPropertyValue("--pie-graph-bg"+i).trim();
    if (color != "" ) {
      bgColors.push(color);
    } else {
      //we don't have enough colors specified so we reuse the ones we have
      i = 0;
      continue;
    }
  }
  return bgColors;
}

var ctx = document.getElementById(chartId);
var myChart = new Chart(ctx, {
  type: "pie",
  data: {
    label: chartTitle,
    datasets: [{
      labels: chartLabels,
      data: chartData,
      backgroundColor: getChartBgColors(chartData.length)
    }]
  },
  options: {
    title: {
      display: true,
      text: chartTitle
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
          var allData = data.datasets[tooltipItem.datasetIndex].data;
          var tooltipLabel = data.labels[tooltipItem.index];
          var tooltipData = allData[tooltipItem.index];
          var total = 0;
          for (var i in allData) {
            total += allData[i];
          }
          var tooltipPercentage = Math.round((tooltipData / total) * 100);
          return tooltipLabel + ": " + tooltipData + " (" + tooltipPercentage + "%)";
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
