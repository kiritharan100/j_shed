<?php  
include("../auth.php");
?>
<?php
require('../db.php');
$grn_id = $_REQUEST['grn_id'];
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
<br>

            <div class="row">
                <!--  page header -->
                <div class="col-lg-12">
                <?php 
                
                     $sel_query = "SELECT oil_grn_master.* ,manage_supplier.supplier_name,client_registration.client_name from oil_grn_master 
                     INNER JOIN
                     manage_supplier
                     ON manage_supplier.sup_id = oil_grn_master.sup_id


                     INNER JOIN client_registration
                      ON client_registration.c_id =oil_grn_master.location 

                     where grn_id = '$grn_id' ";
                    $result = mysqli_query($con, $sel_query);
if ($result === false) {
    // Query failed, handle error
    echo "Database query failed: " . mysqli_error($con);
    exit;  // stop execution if needed
}
                    $rowz = mysqli_fetch_assoc($result);
                    ?>
                    <table style="border:none;">


                       
                        <tr style="border:none;">
                            <td style="border:none;" align="center">
                                <font size="5px"><b> <?php echo $_SESSION['company']; ?> </font><br>
                                <!-- <?php echo $_SESSION['address1']; ?> -->
                                <?php echo $_SESSION['address2']; ?><br>
                                <?php echo $_SESSION['address3']; ?>
                                <?php echo $rowz['client_name'];?>
                                <?php
if($rowz['grn_status'] == 0){
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
                            <td style="border:none;" align="Right">
                                <font size="4px"><b>GOODS RECEIVED NOTE
                                  </font><br><br>
                            </td>
                        </tr>

                        <td style="border:none;" align="right"> <font size="4px"><b>GRN # <?php echo $rowz['loc_id'];?></font> <br>  </td>
                        </tr>

                    </table>
                    

          

                    <table style="border: none;">
                     
                        <tr>
                            <td style="border: none;" width="80px" align="right"> Invoice No </td>
                            <td style="border: none;"> :  <?php echo $rowz['invoice_no'];?>
                            </td>
                        </tr>
                        <tr>
                            <td style="border: none;" width="80px" align="right"> Supplier </td>
                            <td style="border: none;"> : <?php  echo $rowz['supplier_name'];  ?>
                            </td>
                        </tr>
                        <tr>
                            <td style="border: none;" width="80px" align="right"> Date </td>
                            <td style="border: none;"> : <?php echo $rowz['grn_date'];?>
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
                                            
                                        
                                            <th>Sales Price</th>
                                            <th>Qty</th>
                                            <th>Purchase Price</th>
                                            <th>Value</th>
                                        </tr>
                                    </thead>
                                    <tbody>

                                        <?php
                                        $count = 1;

                                   $sel_query = " SELECT oil_stock.* ,product_master.* from oil_stock
                                      INNER JOIN product_master on product_master.p_id = oil_stock.s_id Where oil_stock.grn_id = '$grn_id'";
                                        $result = mysqli_query($con, $sel_query);
                                        $no_items = mysqli_num_rows($result);
                                        while ($row = mysqli_fetch_assoc($result)) { 
                                            $bill_total += ($row['price'] * $row['purchase_qty']);
                                            ?>


                                            <tr>
                                                <td align="center"> <?php echo $count; ?></td>
                                                <td><?php 
                                               echo $row['p_name']."| "; 
                                                  echo " (".$row['p_unit'].") "; 
                                                
                                                  ?> </td> 
                                               
                                                <td align="center"  ><?php echo number_format($row['sales_price'],2); ?></td>
                                                 <td align="center"><?php echo $row['purchase_qty']; ?>  </td>
                                                <td align="center"  ><?php echo number_format($row['price'],2); ?></td>
                                                <td align="right"  ><?php  echo number_format(($row['price'] * $row['purchase_qty']),2); ?></td>
                                            </tr>
                                            

                                        <?php $count++;} ?>
                                        <tr>
                                                <td align="center" colspan="5"> <b>Total Value</b> </td>
                                              
                                                <td align="right"><b><?php echo number_format($bill_total,2); ?></b></td>


                                            </tr>



                                    </tbody>
                                </table>
                            </div>

                            
                            <table width="50%">
                                <tr>
                                    <!-- <td align="center"> <br><br>..............................<br>
                                        Signature of Applicant
                                    </td>
                                    <td align="center"><br> <br>..............................<br>
                                        Recommendation of Branch Head
                                    </td>

                                    <td align="center"><br> <br> ..............................<br>
                                        Signature of authorizing Officer
                                    </td> -->
                                    <td align="center"><br><br> ..............................<br>
                                        Signature of receiving Officer
                                    </td>
                                    <!-- <td align="center"><br><br> ..............................<br>
                                        Signature of issuing Officer
                                    </td> -->

                                </tr>
                            </table>
                            <br><br>
                             GRN By : <?php echo  $rowz['record_by']; ?><br>
                              <?php echo  $rowz['record_date']; ?>  



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