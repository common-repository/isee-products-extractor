"use strict";

function showChart(canvas, data, chart_label) {
  return new Chart(canvas, {
    type: "line",
    data: {
      labels: data.map((row) => {
        const date = new Date(row.monthly_stats);
        return _p2e(
          date.toLocaleDateString("fa-IR", {
            year: "numeric",
            month: "long",
            day: "numeric",
          })
        );
      }),
      datasets: [
        {
          label: chart_label,
          data: data.map((row) => row.overall),
          borderColor: "#E9175E",
          borderWidth: 2,
          fill: false,
          radius: 0.5,
        },
      ],
    },
    // options: {
    //   plugins: {
    //     legend: {
    //       labels: {
    //         font: {
    //           size: 14,
    //           family: "Tahoma",
    //         },
    //       },
    //     },
    //   },
    // },
  });
}

const _p2e = (s) => s.replace(/[۰-۹]/g, (d) => "۰۱۲۳۴۵۶۷۸۹".indexOf(d));
