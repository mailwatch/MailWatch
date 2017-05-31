/**
 * This customizes a line chart for ChartJs. This files requires that the variables chartTitle, chartId, chartFormattedData and chartNumericData, fillBelowLine, COLON are already set.
 */
var lineColors= [
  '#61a9f3', //blue
  '#61E3A9', //green
  '#f381b9', //red
];
var ctx = document.getElementById(chartId);
var myChart = new Chart(ctx, {
  type: "bar",
  data: {
    labels: chartLabels,
    datasets: (function() {
      datasets=[];
      for(i=0;i<chartNumericData.length;i++) {
        datasets.push({
          label: yAxeDescriptions[i],
          data: chartNumericData[i],
          backgroundColor: lineColors[i],
          fill: fillBelowLine[i],
          yAxisID: "y-axis-"+i,
          type: (typeof types !== 'undefined' ? types[i] : "line"),
          showLine: (typeof types !== 'undefined' && fillBelowLine[i] ? true : false),
          pointRadius: 1,
        });
      }
      return datasets;
    })()
  },
  options: {
    title: {
      display: true,
      fontSize: 18,
      text: chartTitle
    },
    legend: {
      display: false,
    },
    elements: {
        line: {
            tension: 0, // disables bezier curves
        }
    },
    scales: {
      yAxes: (function() {
        axes = [];
        for(i=0;i<yAxeDescriptions.length;i++) {
          axes.push({
            id: "y-axis-"+i,
            position: (i%2 == 0 ? "left" : "right" ),
            scaleLabel: {
              display: true,
              labelString: yAxeDescriptions[i]
            },
          });
        }
        return axes;
      })(),
      xAxes: [{
        maxBarThickness: 7,
//        gridLines: {offsetGridLines: false},
        scaleLabel: {
          display: true,
          labelString: xAxeDescription
        }
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
          //COLON specified on main page via php __('colon99')
          var tooltipOutput = " " + yAxeDescriptions[tooltipItem.datasetIndex] + COLON + " " + chartFormattedData[tooltipItem.datasetIndex][tooltipItem.index];
          
          return tooltipOutput;
        }
      }
    },
    hover: { animationDuration: 0 }
  }
});
