/**
 * This customizes a line chart for ChartJs. This files requires that the variables chartTitle, chartId, chartFormattedData and chartNumericData, fillBelowLine, COLON are already set.
 */

var ctx = document.getElementById(chartId);
var myChart = new Chart(ctx, {
  type: "line",
  data: {
    labels: chartLabels,
    datasets: [{
      label: chartTitle,
      data: chartNumericData,
      backgroundColor: '#61a9f3',
      fill: fillBelowLine
    }]
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
      yAxes: [{
        scaleLabel: {
          display: true,
          labelString: yAxeDescription
        }
      }],
      xAxes: [{
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
          var tooltipOutput = " " + yAxeDescription + COLON + " " + chartFormattedData[tooltipItem.index];
          
          return tooltipOutput;
        }
      }
    },
    hover: { animationDuration: 0 }
  }
});
