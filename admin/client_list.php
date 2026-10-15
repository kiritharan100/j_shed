<?php include 'header.php';
require_once '../ensure_business_start_date.php';
ensure_business_start_date_column($con);
?>
<?php 
 
$user_license = $_SESSION['customer'];
  if(isset($_POST['create_new_client'])){
 
   
   $client_id1 = $_POST['client_id'];
   $client_name =$_POST['client_name'];
   $client_type = $_POST['client_type'];
   $client_email = $_POST['client_email'];
   $client_phone = $_POST['client_phone'];
   $contact_name = $_POST['contact_name'];
   $contact_email = $_POST['contact_email'];
   $contact_phone = $_POST['contact_phone'];
   $regNumber = $_POST['regNumber'];
   $business_start_date = mysqli_real_escape_string($con, $_POST['business_start_date'] ?? '');
   $md5_client = md5(time());
  
  $update="INSERT INTO `client_registration`(`md5_client`, `user_license`, `client_id`, `client_name`, `client_type`, `client_email`, `client_phone`, `contact_name`, `contact_email`, `contact_phone`,regNumber,business_start_date) 
   VALUES ('$md5_client','$user_license','$client_id1','$client_name','$client_type','$client_email','$client_phone','$contact_name','$contact_email',' $contact_phone','$regNumber',NULLIF('$business_start_date',''))";
   mysqli_query($con, $update);
  
echo ' <script>  
notify("success","Great Job :","New Client '.$client_name.' Has been added");
</script> ';
}
  
if(isset($_POST['edit_client'])){
   $client_id1 = $_POST['client_id'];
   $client_name =$_POST['client_name'];
   $client_type = $_POST['client_type'];
   $client_email = $_POST['client_email'];
   $client_phone = $_POST['client_phone'];
   $contact_name = $_POST['contact_name'];
   $contact_email = $_POST['contact_email'];
   $contact_phone = $_POST['contact_phone'];
   $regNumber = $_POST['regNumber'];
   $business_start_date = mysqli_real_escape_string($con, $_POST['business_start_date'] ?? '');
    $region = $_POST['region'];
   $md5_client = $_POST['md5_client'];
   $update="UPDATE  client_registration SET  client_id='$client_id1',client_name='$client_name',client_type='$client_type',client_email='$client_email',client_phone='$client_phone'
   ,contact_name='$contact_name',contact_email='$contact_email',contact_phone='$contact_phone',regNumber='$regNumber',region='$region',business_start_date=NULLIF('$business_start_date','') WHERE md5_client ='$md5_client'";
   mysqli_query($con, $update);
  
echo ' <script>  
notify("success","Great Job :","Client '.$client_name.' Has been edited");
</script> ';

}











  ?>
<div class="content-wrapper">
   <!-- Container-fluid starts -->
   <div class="container-fluid">
      <!-- Header Starts -->
      <div class="row">
         <div class="col-sm-12 p-0">
            <div class="main-header">
               <h4>Manage Station  </h4>
               <ol class="breadcrumb breadcrumb-title breadcrumb-arrow">
                  <li class="breadcrumb-item">
                     <a href="index.php">
                        <i class="icofont icofont-home"></i>
                     </a>
                  </li>
                  <li class="breadcrumb-item"><a href="#">Admin  </a>
                  </li>

               </ol>
            </div>
         </div>
      </div>
      <!-- Header end -->

      <div class="row">
         <div class="col-sm-12">

            <div class="card">
               <div class="card-header" align='right'>
                  <a href="#!" class="btn btn-primary waves-effect" data-type="info" data-toggle="modal" data-target="#AddTrailModal"> <i class="fa fa-plus" aria-hidden="true"></i> Add new Center</a>
                  <button type='button' id="exportButton" filename='<?php echo "LIST_".$client_name; ?>.xlsx' class="btn btn-primary"><i class="ti-cloud-down"></i> Export</button>
                  <br>
 <!--* Common Production   supplied by-->
<!--                   
                  <div class="card-block button-list notifications">

                     <a href="#!" class="btn btn-inverse waves-effect" data-type="inverse">Inverse</a>


                     <a href="#!" class="btn btn-info waves-effect" data-type="info">Info</a>


                     <a href="#!" class="btn btn-success waves-effect" data-type="success">Success</a>


                     <a href="#!" class="btn btn-warning waves-effect" data-type="warning">Warning</a>


                     <a href="#!" class="btn btn-danger waves-effect" data-type="danger">Danger</a>

                  </div>  -->



                 

               </div>
               <div class="card-block">
                  <div class="row">
                     <div class="col-sm-12 table-responsive">


                        <table id="example" class="table table-bordered " style="width:100%">
                           <thead>
                              <tr>
                                 <th>#</th>
                                 <th>Center</th>
                                 <th>Id</th>
                                 <th>center Type</th>
                                 <th>District</th><th>Region</th>
                                
                                 <th>Status</th>
                                 <th>Action</th> 
                              </tr>
                           </thead>
                           <tbody>

                           <?php
$count=1;
  $sel_query="SELECT * from client_registration ";
$result = mysqli_query($con,$sel_query);
while($row = mysqli_fetch_assoc($result)) { 
?>
                              <tr <?php  if($row['user_license']==0) {echo "style='background-color:#fe9d9b'";} ?>>
                                 <td><?php echo $count;  $count ++ ; ?></td>
                                 <td><?php echo $row['client_name']; ?></td>
                                 <td><?php echo $row['client_id']; ?></td>
                                 <td><?php echo $row['client_type']; ?></td>
                                 <td><?php echo $row['regNumber']; ?></td>
                                  <td><?php echo $row['region']; ?></td>
                                 
                                 <td><?php  if($row['user_license']==1) {echo "Active";} else { echo "Inactive";}?></td>
                                 <td>  <select class="dropdown_change"  class="form-select"   row_id="<?php echo $row["md5_client"]; ?>"  > 
                                            <option>Select</option>
                                            <option>Active</option> 
                                            <option>Inactive</option>
                                            <option>Edit</option>
                                           </select></td>
                                 
                              </tr>
                              
                              <?php } ?>
                           </tbody>
                        </table>
                        
                        <script>
                            $(".dropdown_change").change(function() {
    var value = $(this).find(":selected").val(); 
    var row_id = $(this).attr("row_id");
    
    if (value === 'Edit') {
        $('#EditClient').modal('show');
        
        $.ajax({
            type: "GET", 
            url: 'ajax/get_edit_client.php', 
            data: {md5_client: row_id}, 
            success: function(response) {
                response = JSON.parse(response); 
                $("#edit-form [name='client_name']").val(response.client_name);
                $("#edit-form [name='client_id']").val(response.client_id);
                $("#edit-form [name='client_type']").val(response.client_type);
                $("#edit-form [name='business_start_date']").val(response.business_start_date || '');
                $("#edit-form [name='client_email']").val(response.client_email);
                $("#edit-form [name='client_phone']").val(response.client_phone);
                $("#edit-form [name='contact_name']").val(response.contact_name);
                $("#edit-form [name='contact_email']").val(response.contact_email);
                $("#edit-form [name='contact_phone']").val(response.contact_phone);
                $("#edit-form [name='regNumber']").val(response.regNumber);
                $("#edit-form [name='region']").val(response.region);
                $("#edit-form [name='md5_client']").val(response.md5_client);
            }
        });
        $(".dropdown_change").val('Select');
    } 
    
    else if (value === 'Active' || value === 'Inactive') {
        // Confirm with SweetAlert2
        Swal.fire({
            title: 'Are you sure?',
            text: `You want to mark this client as ${value}?`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes, change status!',
            cancelButtonText: 'No, cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                // AJAX request to update status
                $.ajax({
                    type: "POST",
                    url: "ajax/update_client_status.php",
                    data: {md5_client: row_id, status: value},
                    success: function(response) {
                        if (response === 'success') {
                            Swal.fire({
                                title: 'Status Updated!',
                                text: `Client is now ${value}`,
                                icon: 'success'
                            });
                            
                            setTimeout(function() {
                                location.reload(); // Refresh the page
                            }, 2000);
                            
                        } else {
                            Swal.fire({
                                title: 'Error!',
                                text: 'Something went wrong while updating the status.',
                                icon: 'error'
                            });
                        }
                    }
                });
            } else {
                // Reset dropdown to "Select" if user cancels
                $(".dropdown_change").val('Select');
            }
        });
    }
});

                        </script>

                       <!-- <script>-->
                       <!-- $(".dropdown_change").change(function() { -->
                       <!--    var value = $(this).find(":selected").val(); -->
                       <!--    var row_id = $(this).attr("row_id");-->
                       <!--    if(value == 'Edit'){-->
                       <!--       $('#EditClient').modal('show');-->
                       <!--       $.ajax({-->
                       <!--             type: "GET", -->
                       <!--             url: 'ajax/get_edit_client.php', -->
                       <!--             data: {md5_client:row_id}, -->
                       <!--             beforeSend: function () { -->
                       <!--             },-->
                       <!--             success: function (response) {-->
                       <!--                   response = JSON.parse(response); -->
                       <!--                   $("#edit-form [name=\"client_name\"]").val(response.client_name);-->
                       <!--                   $("#edit-form [name=\"client_id\"]").val(response.client_id);-->
                       <!--                   $("#edit-form [name=\"client_type\"]").val(response.client_type);-->
                       <!--                   $("#edit-form [name=\"client_email\"]").val(response.client_email);-->
                       <!--                   $("#edit-form [name=\"client_phone\"]").val(response.client_phone);-->
                       <!--                   $("#edit-form [name=\"contact_name\"]").val(response.contact_name);-->
                       <!--                   $("#edit-form [name=\"contact_email\"]").val(response.contact_email);-->
                       <!--                   $("#edit-form [name=\"contact_phone\"]").val(response.contact_phone);-->
                       <!--                   $("#edit-form [name=\"regNumber\"]").val(response.regNumber);-->
                       <!--                       $("#edit-form [name=\"region\"]").val(response.region);-->
                       <!--                   $("#edit-form [name=\"md5_client\"]").val(response.md5_client);-->
                       <!--             }-->

                                   
                       <!--          });-->
                       <!--          $(".dropdown_change").val('Select');-->
                       <!--    }-->

                       <!--  });                           -->
                       <!--</script> -->


                     </div>
                  </div>
               </div>
            </div>



         </div>
      </div>
      <!-- Row end -->
      <!-- Tables end -->
   </div>

   <!-- Container-fluid ends -->
</div>


<?php include 'footer.php'; ?>


<script>
   $(document).ready(function() {
      $('#example').DataTable();
   });

   function lookin2(row_value){
 var c_id = row_value.getAttribute('c_id');
 
 var value = row_value.value;

 $.ajax({
type: "POST", 
url: 'ajax/estimate.php', 
data: {
	select_dmu:c_id
	,c_id:c_id
   ,value:value
  

 
}, 
beforeSend: function () { 
},
success: function (response) {
   notify('success', 'Updated successfully');
} 
}); 
}



</script>

<div class="modal fade" id="AddTrailModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
   <div class="modal-dialog" role="document">
      <div class="modal-content">
         <div class="modal-header">
         <i class="fa fa-refresh" aria-hidden="true"></i>
            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
               <span aria-hidden="true">&times;</span>
            </button>
            <h5 class="modal-title" id="exampleModalLabel">Add New  </h5>
         </div>
         <div class="modal-body" style="padding-left: 20px;">
         <form action="" method="POST">

            
               <div  class="form-group row"><label  class="col-xl-4 col-lg-4 col-md-12 pt-2 label-form">Center ID *</label>
                  <div  class="col-xl-8 col-lg-8 col-md-12 col-12 col-sm-12" style="position: relative;top: 6px;"><input  class="form-control SearchBar noSpl " required="" type="text" placeholder="Type in your Internal Reference ID" name='client_id'></div>
               </div>
               <div  class="form-group row"><label  class="col-xl-4 col-lg-4  col-md-12 pt-3 label-form">
                     
                     <span  class="ng-star-inserted">Center Name *</span>
                  </label>
                  <div  class="col-xl-8 col-lg-8 col-md-12 col-12 col-sm-12" style="position: relative;top: 6px;">
                     <div  class="input-group"><input  class="form-control SearchBar noSpl " maxlength="50" required="" type="text" placeholder="Type Legal Registration name" oninput="this.value = this.value.toUpperCase()" name='client_name'><span  class="input-group-btn" > </div>
                
                  </div>
               </div>
               <div  class="form-group row"><label  class="col-xl-4 col-lg-4 col-md-12 label-form">Center Type *</label>
                  <div  class="col-xl-8 col-lg-8 col-md-12 selectdiv" >
                     <select name='client_type' class="form-control" style="margin:5px;" required name='client_type'>
                        <option value=''> Select </option>
                        <option>Station</option>
                      
                

                     </select>
                  </div>
               </div>
               <div class="form-group row"><label class="col-xl-4 col-lg-4 col-md-12 pt-2 label-form">Business Start Date</label>
                  <div class="col-xl-8 col-lg-8 col-md-12"><input class="form-control" type="date" name="business_start_date"></div>
               </div>
               <div  class="form-group row"><label  class="col-xl-4 col-lg-4 col-md-12 label-form">District *</label>
                  <div  class="col-xl-8 col-lg-8 col-md-12 selectdiv" >
                     <select   class="form-control" style="margin:5px;" required name='regNumber'>
                        <option value=''> Select </option>
                        <option>Trincomalee</option>
                        <option>Batticoloa</option>
                        <option>Ampara</option>
                    
                

                     </select>
                  </div>
               </div>
               
            
             
               <div  class="form-group row"><label  class="col-xl-4 col-lg-4 col-md-12 label-form pt-2">Center Email </label>
                  <div  class="col-xl-8 col-lg-8 col-md-12"><input  class="form-control ng-untouched ng-pristine ng-valid"  pattern="^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,15}$" type="email" placeholder="Type in client email"  name='client_email'>
                     
                  </div>
               </div>
               <div  class="form-group row"><label  class="col-xl-4 col-lg-4 col-md-12 pt-2 label-form">Center Phone Number </label>
                  <div  class="col-xl-8 col-lg-8 col-md-12"><input  class="form-control ng-untouched ng-pristine ng-valid" maxlength="13" minlength="10"   type="text" placeholder="Type in client phone number" name='client_phone'></div>
               </div>
               
               <div  class="form-group row"><label  class="col-xl-4 col-lg-4 col-md-12 pt-2 label-form">Contact Name *</label>
                  <div  class="col-xl-8 col-lg-8 col-md-12"><input  class="form-control SearchBar noSpl " placeholder="Type in contact name"   type="text" name='contact_name'></div>
               </div>
               <div  class="form-group row"><label  class="col-xl-4 col-lg-4 col-md-12 pt-2 label-form">Contact Email *</label>
                  <div  class="col-xl-8 col-lg-8 col-md-12"><input  class="form-control "   pattern="^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,15}$"   type="email" placeholder="Type in contact email" name='contact_email'>
                     <div  class="text-left validation">
                        
                     </div>
                  
                  </div>
               </div>
               
               <div  class="form-group row"><label  class="col-xl-4 col-lg-4 col-md-12 pt-2 label-form">Contact Phone Number</label>
                  <div  class="col-xl-8 col-lg-8 col-md-12"><input  class="form-control ng-untouched ng-pristine ng-valid" maxlength="13" minlength="10"  type="text" placeholder="Type in contact phone number" name='contact_phone'></div>
               </div>
       
      
               
                
            

 
            
         </div>
         <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
            <button type="submit" name='create_new_client' class="btn btn-primary"><i class="fa fa-save"></i> Create</button>
         </div>
         </form>
      </div>
   </div>
</div>


<div class="modal fade" id="EditClient" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
   <div class="modal-dialog" role="document">
      <div class="modal-content">
         <div class="modal-header">
            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
               <span aria-hidden="true">&times;</span>
            </button>
            <h5 class="modal-title" id="exampleModalLabel">Edit Client</h5>
         </div>
         <div class="modal-body" style="padding-left: 20px;">
         <form action="" method="POST" id="edit-form">

            
               <div  class="form-group row"><label  class="col-xl-4 col-lg-4 col-md-12 pt-2 label-form">Center  ID *</label>
                  <div  class="col-xl-8 col-lg-8 col-md-12 col-12 col-sm-12" style="position: relative;top: 6px;">
                  <input  class="form-control SearchBar noSpl " required="" type="text" placeholder="Type in your Internal Reference ID"   name='client_id'>
                  <input type="hidden" name='md5_client' value=''>
               
               </div>
               </div>
               <div  class="form-group row"><label  class="col-xl-4 col-lg-4  col-md-12 pt-3 label-form">
                     
                     <span  class="ng-star-inserted">Center  Name *</span>
                  </label>
                  <div  class="col-xl-8 col-lg-8 col-md-12 col-12 col-sm-12" style="position: relative;top: 6px;">
                     <div  class="input-group"><input  class="form-control SearchBar noSpl " maxlength="50" required="" type="text" placeholder="Type Legal Registration name" oninput="this.value = this.value.toUpperCase()" name='client_name' id='edit-client_name'><span  class="input-group-btn" > </div>
                
                  </div>
               </div>
               <div  class="form-group row"><label  class="col-xl-4 col-lg-4 col-md-12 label-form">Center  Type *</label>
                  <div  class="col-xl-8 col-lg-8 col-md-12 selectdiv" >
                     <select name='client_type' class="form-control" style="margin:5px;" required name='client_type'>
                        <option value=''> Select </option>
                      <option>Station</option>
                

                     </select>
                  </div>
               </div>
               <div class="form-group row"><label class="col-xl-4 col-lg-4 col-md-12 pt-2 label-form">Business Start Date</label>
                  <div class="col-xl-8 col-lg-8 col-md-12"><input class="form-control" type="date" name="business_start_date" id="edit-business_start_date"></div>
               </div>
               
               
               <div  class="form-group row"><label  class="col-xl-4 col-lg-4 col-md-12 label-form">District *</label>
                  <div  class="col-xl-8 col-lg-8 col-md-12 selectdiv" >
                     <select name='regNumber' class="form-control" style="margin:5px;" required   >
                        <option value=''> Select </option>
                        <option>Trincomalee</option>
                        <option>Batticoloa</option>
                        <option>Ampara</option>
                

                     </select>
                  </div>
               </div>
               
 
   <div  class="form-group row"><label  class="col-xl-4 col-lg-4 col-md-12 label-form">Region *</label>
                  <div  class="col-xl-8 col-lg-8 col-md-12 selectdiv" >
                     <select name='region' class="form-control" style="margin:5px;" required   >
                        <option value=''> Select </option>
                        <option>Trincomalee</option>
                        <option>Batticoloa</option>
                        <option>Kalmunai</option>
                        <option>Ampara</option>
                

                     </select>
                  </div>
               </div>
               
               
             
               <div  class="form-group row"><label  class="col-xl-4 col-lg-4 col-md-12 label-form pt-2">Center  Email </label>
                  <div  class="col-xl-8 col-lg-8 col-md-12"><input  class="form-control ng-untouched ng-pristine ng-valid"  pattern="^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,15}$" type="email" placeholder="Type in client email"  name='client_email'>
                     
                  </div>
               </div>
               <div  class="form-group row"><label  class="col-xl-4 col-lg-4 col-md-12 pt-2 label-form">Center  Phone Number </label>
                  <div  class="col-xl-8 col-lg-8 col-md-12"><input  class="form-control ng-untouched ng-pristine ng-valid" maxlength="13" minlength="10"   type="text" placeholder="Type in client phone number" name='client_phone'></div>
               </div>
               
               <div  class="form-group row"><label  class="col-xl-4 col-lg-4 col-md-12 pt-2 label-form">Center  Name *</label>
                  <div  class="col-xl-8 col-lg-8 col-md-12"><input  class="form-control SearchBar noSpl " placeholder="Type in contact name"   type="text" name='contact_name'></div>
               </div>
               <div  class="form-group row"><label  class="col-xl-4 col-lg-4 col-md-12 pt-2 label-form">Contact Email *</label>
                  <div  class="col-xl-8 col-lg-8 col-md-12"><input  class="form-control "   pattern="^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,15}$"   type="email" placeholder="Type in contact email" name='contact_email'>
                     <div  class="text-left validation">
                        
                     </div>
                  
                  </div>
               </div>
               
               <div  class="form-group row"><label  class="col-xl-4 col-lg-4 col-md-12 pt-2 label-form">Contact Phone Number</label>
                  <div  class="col-xl-8 col-lg-8 col-md-12"><input  class="form-control ng-untouched ng-pristine ng-valid" maxlength="13" minlength="10"  type="text" placeholder="Type in contact phone number" name='contact_phone'></div>
               </div>
         </div>
         <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
            <button type="submit" name='edit_client' class="btn btn-primary"><i class="fa fa-save"></i> Save</button>
         </div>
         </form>
      </div>
   </div>
</div>
