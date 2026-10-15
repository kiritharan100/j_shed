<?php 
// header('Location: accounts/');
// exit;
?>

<title><?php echo $_SESSION['company']; ?> </title>
<?php include 'header.php'; ?>

<!-- ============================================================== -->
<!-- wrapper  -->
<!-- ============================================================== -->
<div class="dashboard-wrapper">
    <div class="container-fluid dashboard-content">
        <!-- ============================================================== -->
        <!-- pageheader -->
        <!-- ============================================================== -->
        <div class="row">
            <div class="col-xl-12 col-lg-12 col-md-12 col-sm-12 col-12">
                <div class="page-header">
                    <h2 class="pageheader-title">MAIN MENU </h2>
                    <!-- <p class="pageheader-text">Proin placerat ante duiullam scelerisque a velit ac porta, fusce sit amet vestibulum mi. Morbi lobortis pulvinar quam.</p> -->
                    <div class="page-breadcrumb">
                        <nav aria-label="breadcrumb">
                            <ol class="breadcrumb">
                                <li class="breadcrumb-item"><a href="#" class="breadcrumb-link">Dashboard</a></li>
                                <!-- <li class="breadcrumb-item"><a href="#" class="breadcrumb-link">Menu icon</a></li> -->
                                <li class="breadcrumb-item active" aria-current="page">Menu icon <?php //echo $_SESSION['rights'];
                                                                                                    ?></li>
                            </ol>
                        </nav>
                    </div>
                </div>
            </div>
        </div>
        <!-- ============================================================== -->
        <!-- end pageheader -->
        <!-- ============================================================== -->
        <div class="row">
            <div class="col-xl-12 col-lg-12 col-md-12 col-sm-12 col-12" align="center">
         
            
  <!--           <div id='myElem' style='width:700px;' class="alert alert-Danger alert-dismissible fade show" role="alert">-->
  <!--<strong><h3>You are using trail version of the Software</h3></strong>-->
  <!--<hr>-->
  
//   <?php 

// $date=date('Y-m-d');
// if($date >= '2055-08-01'){
//     echo "Your trail period expired on <h3>Jul 31, 2022</h3>";
//     exit;
// } else {
//      echo "Your trail period will expire on <h3>Jul 31, 2022</h3>";
// }
// ?>

<!--  <button type="button" class="close" data-dismiss="alert" aria-label="Close">-->
<!--    <span aria-hidden="true">&times;</span>-->
<!--  </button>-->
<!--</div>-->

<!--<script>-->
<!--  $("#myElem").show();-->
<!--setTimeout(function() { $("#myElem").hide(); }, 10000);-->

<!--</script>-->





                <table width="80%" align="center">

                    <tr>
                        <td align="center">

                            <a href="accounts/index.php"><img src="img/accounting.png" width="150px"><br><br>
                                <h3>ACCOUNTS</h3>
                            </a>
                        </td>
                       
                    <!-- <td align="center">
                        <a href="store"><img src="img/stock.png" width="100px"><br><br>
                            <h3>INVENTORY</h3>
                        </a>
                    </td> -->
                    <td align="center">
                            <a href="revenue/index.php"><img src="img/income.png" width="150px"><br><br>
                                <h3>REVENUE</h3>
                            </a>
                        </td>

                        <td align="center">
                        <a href="store"><img src="img/inventory.png" width="150px"><br><br>
                            <h3>INVENTORY</h3>
                        </a>
                    </td>
                        <td align="center">
                        <a href="fixed_asset"><img src="img/fixed_asset.png" width="150px"><br><br>
                            <h3>FIXED ASSET</h3>
                        </a>
                    </td>
                       
                    </tr>
                    <tr>
                    <!-- <td align="center">
                        <a href="store"><img src="img/Supply.png" width="100px"><br><br>
                            <h3>PHARMACY</h3>
                        </a> -->
                    </td>
                   
                    
                 

                    </tr>
                </table>
                <br>
                <div class="col-xl-4 col-lg-4 col-md-4 col-sm-4 col-4">
                    .
                </div>

            </div>
        </div>
    </div>

    <?php include 'footer.php'; ?>