<div class="row margin-bottom-12">
    <div class="col-md-12">
        <!--a href="{{URL::action('\LaravelAcl\Authentication\Controllers\GroupController@editGroup')}}" class="btn btn-info pull-right"><i class="fa fa-plus"></i> Add New</a-->
    </div>
</div>
@if( count($groups) > 0 )
<table id="group_table" class="table table-hover">
    <thead>
        <tr>
            <th>Project name</th>
            <th>Description</th>
            <th>Is public</th>
            <th>Operations</th>
        </tr>
    </thead>
    <tbody>
        @foreach($groups as $group)
        <tr>
            <td style="width:20%;padding:0px">{{$group->name}}</td>
            <td style="width:60%;padding:0px">{{$group->description}}</td>
            <td style="width:10%;padding:0px">{{($group->ispublic == 1)? 'Y' : 'N'}}</td>
            <td style="width:10%;padding:0px">
                <a href="{{URL::action('\LaravelAcl\Authentication\Controllers\GroupController@editGroup', ['id' => $group->id])}}"><i class="fa fa-edit fa-2x"></i></a>
                <a href="{{URL::action('\LaravelAcl\Authentication\Controllers\GroupController@deleteGroup',['id' => $group->id, '_token' => csrf_token()])}}" class="margin-left-5 delete"><i class="fa fa-trash-o fa-2x"></i></a>
                <span class="clearfix"></span>
                <!--i class="fa fa-times fa-2x light-blue"></i>
                <i class="fa fa-times fa-2x margin-left-12 light-blue"></i-->
            
            </td>
        </tr>
        @endforeach
    </tbody>
</table>
@else
<span class="text-warning"><h5>No results found.</h5></span>
@endif
