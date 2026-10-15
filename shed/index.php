<?php include 'header.php'; ?>
<?php 
$today = date('Y-m-d');
$que = "UPDATE oil_stock SET  open_status='0' WHERE (purchase_qty-return_qty-issued_qty-adjusted_qty-transfered_qty) = 0  and open_status=1";
mysqli_query($con,$que);


$products = [];
$sql = "
SELECT pm.p_id, pm.p_name, fc.capacity,
       IFNULL(SUM(fsl.debit), 0) AS total_debit,
       IFNULL(SUM(fsl.credit), 0) AS total_credit
FROM product_master pm
LEFT JOIN fuel_capacity fc ON pm.p_id = fc.p_id AND fc.location_id = '$location_id' AND fc.status = 1
LEFT JOIN fuel_stock_ledger fsl ON pm.p_id = fsl.p_id AND fsl.location_id = '$location_id' AND fsl.status = 1
WHERE pm.p_cat = 'Fuel' AND pm.status = 1
GROUP BY pm.p_id
ORDER BY pm.p_id ASC
LIMIT 5";

$res = mysqli_query($con, $sql);
while ($row = mysqli_fetch_assoc($res)) {
     $capacity = $row['capacity'];
    if ($capacity <= 0) {
        continue; // Skip products with zero or undefined capacity
    }

    
    $current_stock = round($row['total_debit'] - $row['total_credit'], 2);
    $capacity = $row['capacity'] > 0 ? $row['capacity'] : 1; // prevent divide by zero
    $percentage = round(($current_stock / $capacity) * 100, 1);
    $products[] = [
        'name' => $row['p_name'],
        'value' => $percentage,
        'unit' => $current_stock . ' L / ' . $row['capacity'].' L'
    ];
}

?>


 <div class="content-wrapper" >
    <div class="container-fluid" >
        
<style>
    .highcharts-figure .chart-container {
    /* width: 300px;
    height: 200px;
    float: left; */
     width: 30%;
    height: 200px;
    float: left;
    margin: 1%;
    box-sizing: border-box;
}

.highcharts-figure,
.highcharts-data-table table {
    width: 100%;
    margin: 0 auto;
}

.highcharts-data-table table {
    font-family: Verdana, sans-serif;
    border-collapse: collapse;
    border: 1px solid #ebebeb;
    margin: 10px auto;
    text-align: center;
    width: 100%;
    max-width: 500px;
}

.highcharts-data-table caption {
    padding: 1em 0;
    font-size: 1.2em;
    color: #555;
}

.highcharts-data-table th {
    font-weight: 600;
    padding: 0.5em;
}

.highcharts-data-table td,
.highcharts-data-table th,
.highcharts-data-table caption {
    padding: 0.5em;
}

.highcharts-data-table thead tr,
.highcharts-data-table tbody tr:nth-child(even) {
    background: #f8f8f8;
}

.highcharts-data-table tr:hover {
    background: #f1f7ff;
}

@media (max-width: 600px) {
    .highcharts-figure,
    .highcharts-data-table table {
        width: 100%;
    }

    .highcharts-figure .chart-container {
        width: 300px;
        float: none;
        margin: 0 auto;
    }
}

.highcharts-description {
    margin: 0.3rem 10px;
}

</style>

<div class="row"  style='padding-top:15px;'>
 
  <div class="col-md-9"  >
        <div class="card">
            <div class="card-block">
                <div class="table-responsive">



                <script src="https://code.highcharts.com/highcharts.js"></script>
<script src="https://code.highcharts.com/highcharts-more.js"></script>
<script src="https://code.highcharts.com/modules/solid-gauge.js"></script>
<script src="https://code.highcharts.com/modules/exporting.js"></script>
<script src="https://code.highcharts.com/modules/export-data.js"></script>
<script src="https://code.highcharts.com/modules/accessibility.js"></script>

<figure class="highcharts-figure">
  <?php foreach ($products as $index => $prod): ?>
    <div id="gauge-<?= $index ?>" class="chart-container"></div>
    <?php if (($index + 1) % 3 == 0): ?>
    <div style="clear:both;"></div>
<?php endif; ?>
<?php endforeach; ?>

   
</figure>


                     
                </div>
            </div>
        </div>





    </div>
    <?php 
    $open_shift_res = mysqli_query($con, "
    SELECT COUNT(*) as total 
    FROM shed_operator_shift 
    WHERE location_id = '$location_id' AND open_status = 1
");

$open_shift_count = 0;
if ($row = mysqli_fetch_assoc($open_shift_res)) {
    $open_shift_count = $row['total'];
}

 $card_sales_q = mysqli_query($con, "
  SELECT 
    COUNT(*) as total_sales, 
    IFNULL(SUM(amount), 0) as total_amount 
  FROM shed_card_sales 
  WHERE location_id = '$location_id' AND day_end = 0 AND status = 1
");

$card_data = mysqli_fetch_assoc($card_sales_q);
$unposted_card_sales = $card_data['total_sales'] ?? 0;
$unposted_card_amount = number_format($card_data['total_amount'], 2);
?>

    
          <div class="col-md-3" style='padding-left:5px;'>

            <div class="card" align='center' style='margin-bottom: 18px;'>
                <div class="card-block">
                    <h5>No of Open Shifts</h5>
                    <h2 style="color:#007bff; font-weight:bold;"><?= $open_shift_count ?></h2>
                </div>
            </div>


            <div class="card text-center" style='margin-bottom: 18px;'>
            <div class="card-block">
                <h5><i class="fa fa-credit-card text-danger"></i> Unposted Card Sales</h5>
                <h5 style="color:#dc3545; font-weight:bold;"><?= $unposted_card_sales ?> (Rs. <?= $unposted_card_amount ?>)</h5>
            </div>
            </div>

                        <?php
                        // Unposted credit sales (only card)
                        $credit_q = mysqli_query($con, "
                        SELECT COUNT(*) as count, IFNULL(SUM(total_sales - paid_amount), 0) as value 
                        FROM shed_credit_sales 
                        WHERE location_id = '$location_id' AND day_end = 0 AND status = 1
                        ");
                        $credit = mysqli_fetch_assoc($credit_q);
                        $credit_count = $credit['count'] ?? 0;
                        $credit_amount = number_format($credit['value'], 2);
                        ?>

                        <div class="card text-center" style='margin-bottom: 18px;'>
                        <div class="card-block">
                            <h5><i class="fa fa-file-text text-danger"></i> Unposted Credit Sales</h5>
                            <h5 style="color:#ec412c; font-weight:bold;"><?= $credit_count ?> (Rs. <?= $credit_amount ?>)</h5>
                        </div>
                        </div>

                                                <?php
                        $oil_sales_q = mysqli_query($con, "
                        SELECT COUNT(*) as count, IFNULL(SUM(issue_total - paid_amount), 0) as value 
                        FROM oil_sales_master 
                        WHERE loc_id = '$location_id' AND day_end = 0 AND issue_status = 1
                        ");

                        $oil = mysqli_fetch_assoc($oil_sales_q);
                        $oil_count = $oil['count'] ?? 0;
                        $oil_amount = number_format($oil['value'], 2);
                        ?><div class="card text-center">
                        <div class="card-block">
                            <h5><i class="fa fa-tint text-info"></i> Unposted Oil Sales</h5>
                            <h5 style="color:#17a2b8; font-weight:bold;"><?= $oil_count ?> (Rs. <?= $oil_amount ?>)</h5>
                        </div>
                        </div> 
            
        </div>


    </div>



 <?php
// Helper: get sales per day-of-month for a given month offset (0 = this month, -1 = last month)
function getSalesByDayOfMonth(mysqli $con, int $location_id, int $offset = 0): array {
    // Determine month start/end in PHP (safer than fiddling with MySQL intervals)
    $start = new DateTime('first day of this month');
    if ($offset !== 0) $start->modify(($offset > 0 ? '+' : '') . $offset . ' month');
    $start->setTime(0, 0, 0);

    $end = clone $start;
    $end->modify('last day of this month')->setTime(23, 59, 59);

    // Prepared query
    $sql = "
        SELECT DAY(date_ended) AS d,
               SUM(fuel_sales + oil_sales) AS total
        FROM day_end_process
        WHERE location_id = ? AND status = 1
          AND date_ended BETWEEN ? AND ?
        GROUP BY d
        ORDER BY d ASC
    ";
    $stmt = $con->prepare($sql);
    $startStr = $start->format('Y-m-d H:i:s');
    $endStr   = $end->format('Y-m-d H:i:s');
    $stmt->bind_param('iss', $location_id, $startStr, $endStr);
    $stmt->execute();
    $res = $stmt->get_result();

    $data = [];
    while ($row = $res->fetch_assoc()) {
        $data[(int)$row['d']] = round((float)$row['total'], 2);
    }
    $stmt->close();

    // Ensure array has entries 1..t (days in this month), missing -> 0
    $daysInMonth = (int)$start->format('t');
    for ($d = 1; $d <= $daysInMonth; $d++) {
        if (!isset($data[$d])) $data[$d] = 0.0;
    }
    ksort($data);
    return $data; // [1 => 1234.50, 2 => 0, 3 => 987.00, ...]
}

// Get datasets
$currentMonthData = getSalesByDayOfMonth($con, (int)$location_id, 0);   // this month
$lastMonthData    = getSalesByDayOfMonth($con, (int)$location_id, -1);  // last month

// Build categories and aligned series by day 01..31
$maxDays = max(count($currentMonthData), count($lastMonthData));
$categories     = [];
$current_series = [];
$last_series    = [];

for ($d = 1; $d <= $maxDays; $d++) {
    $categories[]     = str_pad((string)$d, 2, '0', STR_PAD_LEFT); // "01", "02", ...
    $current_series[] = $currentMonthData[$d] ?? 0.0;
    $last_series[]    = $lastMonthData[$d] ?? 0.0;
}

// (Optional) If you also want a per-day comparison array like:
//   Day "01" => [ 'last' => x, 'current' => y ]
$comparison = [];
for ($d = 1; $d <= $maxDays; $d++) {
    $key = str_pad((string)$d, 2, '0', STR_PAD_LEFT);
    $comparison[$key] = [
        'last'    => $lastMonthData[$d] ?? 0.0,
        'current' => $currentMonthData[$d] ?? 0.0,
    ];
}
?>



    <div class="row"   >
 
 <div class="col-md-12">
    <div class="card">
        <div class="card-block">
            <h5 class="text-center">Sales Trend (Oil + Fuel)</h5>
            <div id="salesTrendChart" style="height: 400px;"></div>
            
        </div>
    </div>
</div>




    </div>

</div>


<script>
    const gaugeOptions = {
    chart: {
        type: 'solidgauge'
    },
    title: null,
    pane: {
        center: ['50%', '85%'],
        size: '140%',
        startAngle: -90,
        endAngle: 90,
        background: {
            backgroundColor:
                Highcharts.defaultOptions.legend.backgroundColor || '#EEE',
            innerRadius: '60%',
            outerRadius: '100%',
            shape: 'arc'
        }
    },
    tooltip: {
        enabled: false
    },
    exporting: {
    enabled: false
},
    yAxis: {
        stops: [
            [0.5, '#55BF3B'], // green
            [0.8, '#DDDF0D'], // yellow
            [1.0, '#DF5353']  // red
        ],
        lineWidth: 0,
        tickWidth: 0,
        minorTickInterval: null,
        tickAmount: 2,
        min: 0,
        max: 100,
        title: {
            y: -70
        },
        labels: {
            y: 16
        }
    },
    plotOptions: {
        solidgauge: {
            dataLabels: {
                y: 5,
                borderWidth: 0,
                useHTML: true
            }
        }
    },
    credits: {
        enabled: false
    }
};

// Chart Data from PHP
const gauges = <?= json_encode($products) ?>;

// Render Gauges
gauges.forEach((prod, i) => {
    let color;
    if (prod.value < 50) {
         color = '#DF5353'; // red
    } else if (prod.value < 80) {
        color = '#DDDF0D'; // yellow
    } else {
       color = '#55BF3B'; // green 
    }

    Highcharts.chart('gauge-' + i, Highcharts.merge(gaugeOptions, {
        yAxis: {
            title: {
               text: '<span style="font-size:19px; font-weight:bold; color:#333; ">' + prod.name + '</span>',
    useHTML: true,
    offset: -20
            },
            stops: [
                [1.0, color]  // solid color based on value
            ]
        },
        series: [{
            name: prod.name,
            data: [prod.value],
            dataLabels: {
                format:
                    '<div style="text-align:center">' +
                    '<span style="font-size:20px">{y}%</span><br/>' +
                    '<span style="font-size:13px;opacity:0.6">' + prod.unit + '</span>' +
                    '</div>'
            }
        }]
    }));
});



    </script>


<script>
Highcharts.chart('salesTrendChart', {
    chart: {
        type: 'line'
    },
    title: {
        text: null
    },
    xAxis: {
        categories: <?= json_encode($categories) ?>,
        title: {
            text: 'Date'
        }
    },
    yAxis: {
        title: {
            text: 'Total Sales (Fuel + Oil)'
        }
    },
    tooltip: {
        shared: true,
        valuePrefix: 'Rs. ',
        valueDecimals: 2
    },
    plotOptions: {
        line: {
            dataLabels: {
                enabled: true
            }
        }
    },
    credits: {
        enabled: false
    },
    series: [{
        name: 'This Month',
        data: <?= json_encode($current_series) ?>
    }, {
        name: 'Last Month',
        data: <?= json_encode($last_series) ?>
    }]
});
</script>


 <?php include 'footer.php';?>