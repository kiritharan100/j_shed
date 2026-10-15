<?php  
include("../auth.php");
?>
<?php
require('../db.php');
$iss_id = $_REQUEST['iss_id'];
$client_language = $_REQUEST['language'];
?>
<html>

<head>
    <title>.</title>
    <style>
        table,
        td,
        th {
            border: 1px solid #ddd;

        }

        table {
            border-collapse: collapse;
            width: 100%;
        }

        th,
        td {
            
            padding: 6px 6px 6px 10px;
        }
    </style>
    <style type="text/css" media="print">
        body {
            zoom: 100%;
        }

        @page {
            margin: 40px 40px 40px 40px;

        }
    </style>
    <script type="text/javascript">
        window.onload = function() {
            window.print();
        }
    </script>
</head>

<body>

    <!--  wrapper -->
    <div id="wrapper">


        <div id="page-wrapper">


            <div class="row">
                <!--  page header -->
                <div class="col-lg-12">
                <?php 
                
                       $sel_query = "SELECT oil_sales_master.* , mange_customer.customer_name, user_license.i_name from oil_sales_master  
                     INNER JOIN mange_customer   ON mange_customer.c_id =oil_sales_master.location 
                    INNER JOIN user_license ON user_license.usr_id = record_by

                     where iss_id = '$iss_id' ";
                    $result = mysqli_query($con, $sel_query);

                    $rowz = mysqli_fetch_assoc($result);


                    
                     $sel_query = "SELECT  mange_customer.customer_name,oil_sales_master.req_id from oil_sales_master   INNER JOIN mange_customer
                        ON mange_customer.c_id =oil_sales_master.to_location   where iss_id = '$iss_id' ";
                   $result = mysqli_query($con, $sel_query);  $rowx = mysqli_fetch_assoc($result);

                    $req_id = $rowx['req_id'];
                    ?>
                    <table style="border:none;"> 
                        <tr style="border:none;">
                            <td style="border:none;" align="center">
                                <font size="5px"><b> <?php echo $_SESSION['company']; ?> </font><br>
                                <?php echo $_SESSION['address1']; ?>
                                <?php echo $_SESSION['address2']; ?><br>
                                <?php echo $_SESSION['address3']; ?>
                                <?php echo $rowz['client_name'];?>
                                <?php
if($rowz['issue_status'] == 0){
    echo "<div style=' width:200px;
    height:80px;
    border-radius:100px;
    font-size:20px;
    color:#fff;
    line-height:80px;
    text-align:center;
    background:red';><h1>Cancelled </h1></div>";
}
 ?>
                            </td>
                        </tr>
                        <tr style="border:none;">
                            <td style="border:none;" align="center">
                                <font size="4px"><b>INVOICE
                                  </font><br> 
                            </td>
                        </tr>

                        <td style="border:none;" align="right"> <font size="3px"><b>Invoice No : # 0<?php echo $rowz['loc_id'];?></font> <br>  </td>
                        </tr>

                    </table>
                    

          

                    <table style="border: none;">
                     
                        <tr>
                            <td style="border: none;" width="100px" align="right"> Customer </td>
                            <td style="border: none;"> : <?php  echo $rowx['customer_name'];  ?>
                            </td>
                        </tr>
                        <tr>
                            <td style="border: none;" width="100px" align="right"> Date </td>
                            <td style="border: none;"> : <?php echo $rowz['issue_date'];?>
                            </td>
                        </tr>
                       
                    </table>

                </div>
                <!-- end  page header -->
            </div>
            <div class="row">
                <div class="col-lg-12">
                    <!-- Advanced Tables -->
                    <div class="panel panel-default">
                        <div class="panel-heading">


                        </div>
                        <div class="panel-body">
                            <div class="table-responsive">
                                <table>
                                    <thead>
                                        <tr bgcolor='#A9BEFB'>
                                            <th>#</th>
                                            <th> Details of Supply</th>
                                            
                                         
                                            
                                            <th>Qty</th>
                                            <th>Rate</th>
                                            <th>Value</th>
                                        </tr>
                                    </thead>
                                    <tbody>

                                        <?php
                                        $count = 1;

                                   $sel_query = " SELECT oil_sales_detail.* ,product_master.*,oil_stock.price,oil_stock.exp_date from oil_sales_detail

                                   


                                   INNER JOIN oil_stock ON oil_sales_detail.batch_id = oil_stock.batch_id



                                      INNER JOIN product_master on product_master.p_id = oil_stock.s_id Where oil_sales_detail.iss_id = '$iss_id'";
                                        $result = mysqli_query($con, $sel_query);
                                        
if (!$result) {
    die("Query Failed: " . mysqli_error($conn)); // Show the error and stop execution
}


                                        $no_items = mysqli_num_rows($result);
                                        
                                        while ($row = mysqli_fetch_assoc($result)) { 
                                            $bill_total += ($row['rate'] * $row['qty']);
                                            ?>


                                            <tr>
                                                <td align="center"> <?php echo $count; ?></td>
                                                <td><?php echo $row['p_name']; 
                                                 
                                                  echo " ".$row['m_unit'];?> </td> 
                                            
                                           
                                                 <td align="center"><?php echo $row['issue_qty']; ?>  </td>
                                                <td align="center"  ><?php echo number_format($row['sales_price'],2); ?></td>
                                                <td align="right" ><?php $bill_total += ($row['sales_price'] * $row['issue_qty']);  echo number_format(($row['sales_price'] * $row['issue_qty']),2); ?></td>
                                            </tr>
                                            

                                        <?php $count++;} ?>
                                        <tr>
                                                <td align="center" colspan="4"> <b>Total</b> </td>
                                              
                                                <td align="right"><b><?php echo number_format($bill_total,2); ?></b></td>


                                            </tr>



                                    </tbody>
                                </table>
                            </div>
<br><br>

                            
                            <table width="50%" style='border:none;' >
                                <tr style='border:none;'>
                                    <!-- <td align="center"> <br><br>..............................<br>
                                        Signature of Applicant
                                    </td>
                                    <td align="center"><br> <br>..............................<br>
                                        Recommendation of Branch Head
                                    </td>

                                    <td align="center"><br> <br> ..............................<br>
                                        Signature of authorizing Officer
                                    </td> -->
                                    <td align="left" style='border:none;'> 
                                    Record By : <?php echo  $rowz['i_name']; ?><br>
                              <?php echo  $rowz['record_date']; ?>  
                                    </td>
                                    <td align="center" style='border:none;'> 
                                   
                                    </td>
                                

                                </tr>
                            </table>
                            <br><br>
                            



                        </div>
                    </div>
                    <!--End Advanced Tables -->
                </div>
            </div>
            <div class="row">
                <div class="col-lg-6">


                </div>
                <!-- end wrapper -->


</body>

</html>