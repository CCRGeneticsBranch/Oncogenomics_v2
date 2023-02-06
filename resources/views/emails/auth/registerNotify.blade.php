<H3>User <font color=red>{{$name}}</font> has logged in Oncogenomics first time<br>Please <a href='{{url("/admin/users/edit?id=$id")}}'>grant</a> this user permission</H3>
<H4>User information:</H4>
<table border=1 cellspacing="2" width="60%">
	<tr><th>Name</th><td>{{$name}}</td></tr>
	<tr><th>Email</th><td>{{$email}}</td></tr>
	<tr><th>Institute</th><td>{{$department}}</td></tr>
	<tr><th>Phone</th><td>{{$tel}}</td></tr>
	<tr><th>Project/Protocol</th><td>{{$project}}</td></tr>
	<tr><th>Reason for Access</th><td>{{$reason}}</td></tr>
</table>


