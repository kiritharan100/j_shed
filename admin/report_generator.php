<?php include 'header.php'; ?>
<div class="content-wrapper">
   <!-- Container-fluid starts -->
   <div class="container-fluid">
      <!-- Header Starts -->
 
      <!-- Header end -->

      <div class="row">
         <div class="col-sm-12">
                    <!--  page header --><br><br>
                    <div class='alert alert-info' align='center'>
                        <font size=4>

                            Report Generator

                        </font>
                    </div>
                </div>
            </div>


            <div class="row">


                <div class="col-lg-6">
                    <!-- Advanced Tables -->

                    <div class="panel panel-default">

                        <div class="panel-body">
                            <div class="table-responsive">




 














                                <table class="table" id="dataTables-example">
                                    <thead>
                                        <tr>
                                            <th>No</th>
                                            <th>Report Name</th>
                                            <th>Action</th>

                                        </tr>
                                    </thead>
                                    <tbody>
    <!--<tr>-->
    <!--                                    <td align="center">01</td>-->
    <!--                                    <td>Stock Balance  </td>-->
    <!--                                   <td align="center"><a href="report_generator.php?stock"><button type="button" class="btn btn-info">Generate</button></td>-->
    <!--                                    </tr>  -->
                                        
                                        <tr>
                                            <td align="center">02</td>
                                            <td>Total Quarterly Medicine Requirements</td>
                                            <td align="center"><a href="report_generator.php?total_medicine" <button type="button" class="btn btn-info">Generate</button></td>
                                        </tr>
                                        <!--<tr>-->
                                        <!--    <td align="center">03</td>-->
                                        <!--    <td>Sales Details</td>-->
                                        <!--    <td align="center"><a href="report_generator.php?sale_detail" <button type="button" class="btn btn-info">Generate</button></td>-->
                                        <!--</tr>-->
                                        <!--<tr>-->
                                        <!--    <td align="center">04</td>-->
                                        <!--    <td>Stock Adjustment</td>-->
                                        <!--    <td align="center"><a href="report_generator.php?sAdjustment" <button type="button" class="btn btn-info">Generate</button></td>-->
                                        <!--</tr>-->


                                        
                                        <!--<tr>-->

                                        <!--<td align="center">05</td>-->
                                        <!--<td>Customer age analysis</td>-->
                                        <!--<td align="center"><a href="report_generator.php?age" <button type="button" class="btn btn-info">Generate</button></td>-->
                                        <!--</tr>-->
                                        <!--<td align="center">06</td>-->
                                        <!--<td>Customer Balance Summary</td>-->
                                        <!--<td align="center"><a href="report_generator.php?RecoveredIncomeSummary" <button type="button" class="btn btn-info">Generate</button></td>-->
                                        <!--</tr>-->
                                        <!--<td align="center">07</td>-->
                                        <!--<td>Customer Balance Detail </td>-->
                                        <!--<td align="center"><a href="report_generator.php?UNRecoveredIncome" <button type="button" class="btn btn-info">Generate</button></td>-->
                                        <!--</tr>-->


                                        <!--<tr>-->
                                        <!--    <td align="center">08</td>-->
                                        <!--    <td>Invoice List</td>-->
                                        <!--    <td align="center"><a href="report_generator.php?invoice_list" <button type="button" class="btn btn-info">Generate</button></td>-->
                                        <!--</tr>-->

                                        <!--<tr>-->
                                        <!--    <td align="center">09</td>-->
                                        <!--    <td>Receipt List</td>-->
                                        <!--    <td align="center"><a href="report_generator.php?receipt_list" <button type="button" class="btn btn-info">Generate</button></td>-->
                                        <!--</tr>-->

                                        <!--<tr>-->
                                        <!--    <td align="center">10</td>-->
                                        <!--    <td>Receipt with Settlement detail</td>-->
                                        <!--    <td align="center"><a href="report_generator.php?receipt_detail" <button type="button" class="btn btn-info">Generate</button></td>-->
                                        <!--</tr>-->

                                        <!--<tr>-->
                                        <!--    <td align="center">11</td>-->
                                        <!--    <td>Customer List</td>-->
                                        <!--    <td align="center">-->
                                            
                                        <!--</tr>-->
                                        <!--<td align="center">12</td>-->
                                        <!--<td>Summary Invoice & Receipt (Monthly)</td>-->
                                        <!--<td align="center"><a href="report_generator.php?SummaryBilling" <button type="button" class="btn btn-info">Generate</button></td>-->
                                        <!--</tr>-->
                                      
                                        <!--<td align="center">13</td>-->
                                        <!--<td>Receivable Movement </td>-->
                                        <!--<td align="center"><a href="report_generator.php?UNRecoveredIncomeDetail" <button type="button" class="btn btn-info">Generate</button></td>-->
                                        <!--</tr>-->
                                        
                             

                                    </tbody>
                                </table>
                            </div>

                        </div>
                    </div>
                </div>
<script>
      $('#example').dataTable( {
"pageLength": 25
} );
    </script>
                <?php if (isset($_REQUEST['total_medicine'])) { ?>
                    <div class="col-lg-6">
                        <!--  page header --><br><br>
                        <div class='alert alert-info' align='center'>
                            <font size=4>
                            Total Quarterly Medicine Requirements
                                <form action="report/drugs_estimate.php" target="_balnk">
                                    <table><tr><td>
                                    Year :</td><td><input type="number" name="year" required value="<?php echo  date('Y'); ?>"></td></tr><tr><td>
                                    For  :</td><td><select name ='for'>
                                        <option>ALL</option>
                                        <option>NAWINNA</option>
                                        <option>DMU KAPALTHURAI</option>
                                        <option>DMU SATHURUKONDAN</option>
                                        <option>DMU AKKARAIPATU </option>
                                    </select></td></tr><tr><td></td><td>


                                    <input class="btn btn-primary" type="submit" value="View"></td>

                                </form>
                            </font>
                        </div>
                    </div>
                <?php } ?>


                <?php if (isset($_REQUEST['stock'])) { ?>
                    <div class="col-lg-6">
                        <!--  page header --><br><br>
                        <div class='alert alert-info' align='center'>
                            <font size=4>
                             Stock Balance 
                                <form action="report/stock_balance.php" target="_balnksm"><br>
                             <table><tr><td width='50%'>Category of Stock:</td><td> <select required name="cat" class="form-control">
            <option>All</option>  
 


</select></td></tr>
<tr>
    <td>Batch Wise Stock</td><td>
        <input type="checkbox" id="vehicle1" name="batch_wise" value="1">
    </td>
</tr>

</table>


                                    <input class="btn btn-primary" type="submit" value="View ">

                                </form>
                            </font>
                        </div>
                  
                    </div>
                <?php } ?>



                <?php if (isset($_REQUEST['age'])) { ?>
                    <div class="col-lg-6">
                        <!--  page header --><br><br>
                        <div class='alert alert-info' align='center'>
                            <font size=4>
                                Customer age analysis
                                <form action="report/print_debtors_age_analysis.php" target="_balnkA">

                                    On :<input type="date" name="on" required value="<?php echo  date('Y-m-d'); ?>">

                                    <!-- <input class="btn btn-info" type="submit" value="View"  formaction="report/view_debtors_age_analysis.php" target="_blank" > -->
                                    <input class="btn btn-primary" type="submit" value="Print">

                                </form>
                            </font>
                        </div>
                    </div>
                <?php } ?>

                <?php if (isset($_REQUEST['AuditFeeReceivedAge'])) { ?>
                    <div class="col-lg-6">
                        <!--  page header --><br><br>
                        <div class='alert alert-info' align='center'>
                            <font size=4>
                                Audit fee received (Year of Invoice) 
                                <form action="report/print_age_of_receipt_category.php" target="_balnkB">

                                    Year :<input type="text" name="on" required value="<?php echo  date('Y', strtotime("-1 year")) ?>">

                                    <!-- <input class="btn btn-info" type="submit" value="View"  formaction="report/view_RecoveredIncomeSummary.php" target="_blank" > -->
                                    <input class="btn btn-primary" type="submit" value="Print">

                                </form>
                            </font>
                        </div>
                        <div class='alert alert-info' align='center'>
                            <font size=4>
                                Audit fee received (Year of Invoice) Summary with Total
                                <form action="report/print_age_of_receipt_category1.php" target="_balnkcss">

                                    Year :<input type="text" name="on" required value="<?php echo  date('Y', strtotime("-1 year")) ?>">

                                    <!-- <input class="btn btn-info" type="submit" value="View"  formaction="report/view_RecoveredIncomeSummary.php" target="_blank" > -->
                                    <input class="btn btn-primary" type="submit" value="Print">

                                </form>
                            </font>
                        </div>
                    </div>
                <?php } ?>


                <?php if (isset($_REQUEST['RecoveredIncomeSummary'])) { ?>
                    <div class="col-lg-6">
                        <!--  page header --><br><br>
                        <div class='alert alert-info' align='center'>
                            <font size=4>
                                Customer Balance  As at
                                <form action="report/print_UNRecoveredIncomeSummary.php" target="_balnkC">

                                    On :<input type="date" name="on" required value="<?php echo  date('Y-m-d'); ?>">

                                    <!-- <input class="btn btn-info" type="submit" value="View"  formaction="report/view_RecoveredIncomeSummary.php" target="_blank" > -->
                                    <input class="btn btn-primary" type="submit" value="Print">

                                </form>
                            </font>
                        </div>
                    </div>

                    <div class="col-lg-6">
                        <!--  page header --><br><br>
                        <div class='alert alert-info' align='center'>
                            <font size=4>
                                Customer Balance Yearly 
                                <form action="report/print_UNRecoveredIncomeSummary1.php" target="_balnkD">

                                    On :<input type="date" name="on" required value="<?php echo  date('Y-m-d'); ?>">
<br>
                                    Category <br>
                                    <select name="category" >
                                        <option>All</option>
                                  
                                    </select><br>

                                    <!-- <input class="btn btn-info" type="submit" value="View"  formaction="report/view_RecoveredIncomeSummary.php" target="_blank" > -->
                                    <input class="btn btn-primary" type="submit" value="Print">

                                </form>
                            </font>
                        </div>
                        
                    </div>
                <?php } ?>


                <?php if (isset($_REQUEST['UNRecoveredIncome'])) { ?>
                    <div class="col-lg-6">
                        <!--  page header --><br><br>
                        <div class='alert alert-info' align='center'>
                            <font size=4>
                                Customer Balance Detail
                                <form action="report/print_UNRecoveredIncome.php" target="_balnkE">

                                    On :<input type="date" name="on" required value="<?php echo  date('Y-m-d'); ?>">

                                    <!-- <input class="btn btn-info" type="submit" value="View"  formaction="report/view_RecoveredIncomeSummary.php" target="_blank" > -->
                                    <input class="btn btn-primary" type="submit" value="Print">

                                </form>
                            </font>
                        </div>
                    </div>
                <?php } ?>
                <?php if (isset($_REQUEST['UNRecoveredIncomeDetail'])) { ?>
                    <div class="col-lg-6">
                        <!--  page header --><br><br>
                        <div class='alert alert-info' align='center'>
                            <font size=4>
                                Arrias revenue Detail report
                                <form action="report/print_UNRecoveredIncomeDetail.php" target="_balnkF">

                                    As at :<input type="date" name="on" required value="<?php echo  date('Y-m-d'); ?>">

                                    <!-- <input class="btn btn-info" type="submit" value="View"  formaction="report/view_RecoveredIncomeSummary.php" target="_blank" > -->
                                    <input class="btn btn-primary" type="submit" value="Print">

                                </form>
                            </font>
                        </div>
                    </div>
                <?php } ?>

                <?php if (isset($_REQUEST['VAT'])) { ?>
                    <div class="col-lg-6">
                        <!--  page header --><br><br>
                        <div class='alert alert-info' align='center'>
                            <font size=4>
                                VAT Output Shedule
                                <form action="report/print_vat_shedule.php" target="_balnkG">

                                    From :<input type="date" name="from" required value="<?php echo $start = (new DateTime('first day of -' . ((date('n') % 3) + 5) . ' month midnight'))->format('Y-m-d'); ?>">
                                    To :<input type="date" name="to" required value="<?php echo  $end = (new DateTime('last day of -' . ((date('n') % 3) + 3) . ' month midnight'))->format('Y-m-d'); ?>">
                                    <br>
                                    <!-- <input class="btn btn-info" type="submit" value="View"  formaction="report/view_invoice_list.php" target="_blank" > -->
                                    <input class="btn btn-primary" type="submit" value="Print">

                                </form>
                            </font>
                        </div>
                    </div>
                <?php } ?>

                <?php if (isset($_REQUEST['progress'])) { ?>
                    <div class="col-lg-6">
                        <!--  page header --><br><br>
                        <div class='alert alert-info' align='center'>
                            <font size=4>
                                Audit fees progress
                                <form action="report/audit_fee_progress_list.php" target="_balnkH">

                                    Audit Year:<input type="number" name="year" required value="<?php echo  date('Y', strtotime("-1 year")); ?>">
                                    
                                    <br>

                              
                                    <!-- <input class="btn btn-info" type="submit" value="View"  formaction="report/view_invoice_list.php" target="_blank" > -->
                                    <input class="btn btn-primary" type="submit" value="Print">

                                </form>
                            </font>
                        </div>
                    </div>
                <?php } ?>

                <?php if (isset($_REQUEST['invoice_list'])) { ?>
                    <div class="col-lg-6">
                        <!--  page header --><br><br>
                        <div class='alert alert-info' align='center'>
                            <font size=4>
                                Invoice List
                                <form action="report/print_invoice_list.php" target="_balnkH">

                                    From :<input type="date" name="from" required value="<?php echo  date('Y', strtotime("-1 year")) . "-01-01"; ?>">
                                    To :<input type="date" name="to" required value="<?php echo  date('Y-m-d'); ?>">
                                    <br>

                                    <input type='checkbox' class='post' name='outstanding' value='1'> Currect Outstanding<br>
                                    <!-- <input class="btn btn-info" type="submit" value="View"  formaction="report/view_invoice_list.php" target="_blank" > -->
                                    <input class="btn btn-primary" type="submit" value="Print">

                                </form>
                            </font>
                        </div>
                    </div>
                <?php } ?>
                <?php if (isset($_REQUEST['sale_list'])) { ?>
                    <div class="col-lg-6">
                        <!--  page header --><br><br>
                        <div class='alert alert-info' align='center'>
                            <font size=4>
                               Item wise Sales
                                <form action="report/sales.php" target="_balnkH">

                                    From :<input type="date" name="from" required value="<?php echo  date('Y', strtotime("-1 year")) . "-01-01"; ?>">
                                    To :<input type="date" name="to" required value="<?php echo  date('Y-m-d'); ?>">
                                    <br>

                                  
                                    <!-- <input class="btn btn-info" type="submit" value="View"  formaction="report/view_invoice_list.php" target="_blank" > -->
                                    <input class="btn btn-primary" type="submit" value="Print">

                                </form>
                            </font>
                        </div>
                    </div>
                <?php } ?>
                <?php if (isset($_REQUEST['sale_detail'])) { ?>
                    <div class="col-lg-6">
                        <!--  page header --><br><br>
                        <div class='alert alert-info' align='center'>
                            <font size=4>
                               Sales Details
                                <form action="report/stock_issue_detail.php" target="_balnkH">

                                    From :<input type="date" name="from" required value="<?php echo  date('Y', strtotime("-1 year")) . "-01-01"; ?>">
                                    To :<input type="date" name="to" required value="<?php echo  date('Y-m-d'); ?>">
                                    <br>

                                  
                                    <!-- <input class="btn btn-info" type="submit" value="View"  formaction="report/view_invoice_list.php" target="_blank" > -->
                                    <input class="btn btn-primary" type="submit" value="Print">

                                </form>
                            </font>
                        </div>
                    </div>
                <?php } ?>
                <?php if (isset($_REQUEST['sAdjustment'])) { ?>
                    <div class="col-lg-6">
                        <!--  page header --><br><br>
                        <div class='alert alert-info' align='center'>
                            <font size=4>
                               Stock Adjustment
                                <form action="report/print_stock_adjustment.php" target="_balnkH">

                                    From :<input type="date" name="from" required value="<?php echo  date('Y', strtotime("-1 year")) . "-01-01"; ?>">
                                    To :<input type="date" name="to" required value="<?php echo  date('Y-m-d'); ?>">
                                    <br>

                                  
                                    <!-- <input class="btn btn-info" type="submit" value="View"  formaction="report/view_invoice_list.php" target="_blank" > -->
                                    <input class="btn btn-primary" type="submit" value="Print">

                                </form>
                            </font>
                        </div>
                    </div>
                <?php } ?>




                <?php if (isset($_REQUEST['receipt_list'])) { ?>
                    <div class="col-lg-6">
                        <!--  page header --><br><br>
                        <div class='alert alert-info' align='center'>
                            <font size=4>
                                Receipt List
                                <form action="report/print_receipt_list.php" target="_balnkI">

                                    From :<input type="date" name="from" required value="<?php echo  date('Y', strtotime("-1 year")) . "-01-01"; ?>">
                                    To :<input type="date" name="to" required value="<?php echo  date('Y-m-d'); ?>">
                                    <br>
                                    <!-- <input class="btn btn-info" type="submit" value="View"  formaction="report/view_invoice_list.php" target="_blank" > -->
                                    <input class="btn btn-primary" type="submit" value="Print">

                                </form>
                            </font>
                        </div>
                    </div>
                <?php } ?>

                <?php if (isset($_REQUEST['receipt_detail'])) { ?>
                    <div class="col-lg-6">
                        <!--  page header --><br><br>
                        <div class='alert alert-info' align='center'>
                            <font size=4>
                                Receipt with Settlement detail
                                <form action="report/print_receipt_detail.php" target="_balnkJ">

                                    From :<input type="date" name="from" required value="<?php echo  date('Y', strtotime("-1 year")) . "-01-01"; ?>">
                                    To :<input type="date" name="to" required value="<?php echo  date('Y-m-d'); ?>">
                                    <br>
                                    <!-- <input class="btn btn-info" type="submit" value="View"  formaction="report/view_invoice_list.php" target="_blank" > -->
                                    <input class="btn btn-primary" type="submit" value="Print">

                                </form>
                            </font>
                        </div>
                    </div>
                <?php } ?>

                <?php if (isset($_REQUEST['tax_paid'])) { ?>
                    <div class="col-lg-6">
                        <!--  page header --><br><br>
                        <div class='alert alert-info' align='center'>
                            <font size=4>
                                Tax Paid<br>
                                Under Construction<br>
                                <img src='images/under_con.png' width="200px">
                                <!--
				<form action="report/print_receipt_detail.php" target="_balnk">
					
						From   :<input  type="date" name="from" required value="<?php echo  date('Y') . "-01-01"; ?>">
						To  :<input  type="date" name="to" required value="<?php echo  date('Y') . "-12-31"; ?>">
                        <br>
                        <input class="btn btn-info" type="submit" value="View"  formaction="report/view_invoice_list.php" target="_blank" >
                        <input class="btn btn-primary" type="submit" value="Print"  >
                        
						</form>	-->
                            </font>
                        </div>
                    </div>
                <?php } ?>


                <?php if (isset($_REQUEST['unpaid_invoice'])) { ?>
                    <div class="col-lg-6">
                        <!--  page header --><br><br>
                        <div class='alert alert-info' align='center'>
                            <font size=4>
                                Outstanding Audit fee (Client's Audit Year Wise)
                                <form action="report/print_unpaid_invoice.php" target="_balnka"><br>

                                    Audit Year :<input type="text" name="on" required value="<?php echo  date('Y', strtotime("-1 year")) ?>">
                                    <br>

                                    Branch Code :<input type='checkbox' class='post' name='branch_code' checked value='1'> <br>
                                    Type of Audit : <input type='checkbox' class='post' name='type' value='1'><br>

                                    Category <br>
                                    <select multiple name="select[]" id="sel2" required size="7">>
                                        <option>Agrarian Service Centres</option>
                                        <option>Economic Centres</option>
                                        <option>Municipal Councils</option>
                                        <option>Pradeshiya Saba</option>
                                        <option>Private and Direct Audits</option>
                                        <option>Urban Councils</option>
                                    </select><br>

                                    (Mutiple select list hold Ctrl to select more than one)
                                    <!-- <input class="btn btn-info" type="submit" value="View"  formaction="report/view_invoice_list.php" target="_blank" > -->
                                    <input class="btn btn-primary" type="submit" value="Print">

                                </form>
                            </font>
                        </div>
                    </div>
                <?php } ?>


                <?php if (isset($_REQUEST['received_audit_fee'])) { ?>
                    <div class="col-lg-6">
                        <!--  page header --><br><br>
                        <div class='alert alert-info' align='center'>
                            <font size=4>
                                Received Audit fee (Client's Audit Year Wise)
                                <form action="report/print_received_audit_fee.php" target="_balnkmm"><br>

                                    Audit Year :<input type="text" name="on" required value="<?php echo  date('Y', strtotime("-1 year")) ?>">
                                    <br>

                                    Branch Code :<input type='checkbox' class='post' name='branch_code' checked value='1'> <br>
                                    Type of Audit : <input type='checkbox' class='post' name='type' value='1'><br>

                                    Category <br>
                                    <select multiple name="select[]" id="sel2" required size="7">>
                                        <option>Agrarian Service Centres</option>
                                        <option>Economic Centres</option>
                                        <option>Municipal Councils</option>
                                        <option>Pradeshiya Saba</option>
                                        <option>Private and Direct Audits</option>
                                        <option>Urban Councils</option>
                                    </select><br>

                                    (Mutiple select list hold Ctrl to select more than one)
                                    <!-- <input class="btn btn-info" type="submit" value="View"  formaction="report/view_invoice_list.php" target="_blank" > -->
                                    <input class="btn btn-primary" type="submit" value="Print">

                                </form>
                            </font>
                        </div>
                    </div>
                <?php } ?>


                <?php if (isset($_REQUEST['pending_estimate'])) { ?>
                    <div class="col-lg-6">
                        <!--  page header -->
                        <div class='alert alert-info' align='center'>
                            <font size=4>
                                Pending Ministry approval on Concurrence <br>

                                (Client's Audit Year Wise)
                                <form action="report/print_pending_concurrance.php" target="_balnknn"><br>

                                    Audit Year :<input type="text" name="on" required value="<?php echo  date('Y', strtotime("-1 year")) ?>">
                                    <br>

                                    Branch Code :<input type='checkbox' class='post' name='branch_code' checked value='1'> <br>
                                    Type of Audit : <input type='checkbox' class='post' name='type' value='1'><br>

                                    Category <br>
                                    <select multiple name="select[]" id="sel2" required size="7">>
                                        <option>Agrarian Service Centres</option>
                                        <option>Economic Centres</option>
                                        <option>Municipal Councils</option>
                                        <option>Pradeshiya Saba</option>
                                        <option>Private and Direct Audits</option>
                                        <option>Urban Councils</option>
                                    </select><br>

                                    (Mutiple select list hold Ctrl to select more than one)
                                    <!-- <input class="btn btn-info" type="submit" value="View"  formaction="report/view_invoice_list.php" target="_blank" > -->
                                    <input class="btn btn-primary" type="submit" value="Print">

                                </form>
                            </font>
                        </div>
                    </div>

                    <div class="col-lg-6">
                        <!--  page header -->
                        <div class='alert alert-info' align='center'>
                            <font size=4>
                                Pending Ministry approval on Concurrence By Send Date
                                <form action="report/print_pending_concurrance_bydate.php" target="_balnkJ">

                                    From :<input type="date" name="from" required value="<?php echo  date('Y', strtotime("-1 year")) . "-01-01"; ?>">
                                    To :<input type="date" name="to" required value="<?php echo  date('Y-m-d'); ?>">
                                    <br>
                                    Branch Code :<input type='checkbox' class='post' name='branch_code' checked value='1'> <br>
                                    Type of Audit : <input type='checkbox' class='post' name='type' value='1'><br>

                                    Category <br>
                                    <select multiple name="select[]" id="sel2" required size="7">>
                                        <option>Agrarian Service Centres</option>
                                        <option>Economic Centres</option>
                                        <option>Municipal Councils</option>
                                        <option>Pradeshiya Saba</option>
                                        <option>Private and Direct Audits</option>
                                        <option>Urban Councils</option>
                                    </select><br>

                                    (Mutiple select list hold Ctrl to select more than one)
                                    <!-- <input class="btn btn-info" type="submit" value="View"  formaction="report/view_invoice_list.php" target="_blank" > -->
                                    <input class="btn btn-primary" type="submit" value="Print">

                                </form>
                            </font>
                        </div>
                    </div>


                <?php } ?>


            </div>
        </div>
        <?php include 'footer.php'; ?>
      

</body>

</html>