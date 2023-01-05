{{-- add group --}}
<div id='loading' class='loading_img' style="display: none;">
    <h4 style="display:inline">Processing...</h4><img width=30 height=30 src='{{url('/images/ajax-loader.gif')}}'></img>
</div>
@if (count($projects) > 0)
<H5>( Select project and click <span class="glyphicon glyphicon-plus-sign add-input"></span> to add project )</H5>
{{Form::open(["action" => "\LaravelAcl\Authentication\Controllers\UserController@addGroup", 'class' => 'form-add-group', 'role' => 'form'])}}
<div class="form-group">
    <div class="input-group">        
        <span class="input-group-addon form-button button-add-group"><span class="glyphicon glyphicon-plus-sign add-input"></span></span>
        {{Form::select('group_id', $projects, '', ["class"=>"form-control"])}}
        {{Form::hidden('id', $user->id)}}        
    </div>
    <span class="text-danger">{{$errors->first('name')}}</span>
</div>
{{Form::hidden('id', $user->id)}}
@if(! $user->exists)
<div class="form-group">
    <span class="text-danger"><h5>You need to create the user first.</h5></span>
</div>
@endif
{{Form::close()}}
@endif
{{-- delete group --}}
@if( ! $user->groups->isEmpty() )
<H5>( Current projects granted. Click <span class="glyphicon glyphicon-minus-sign add-input"></span> to remove the project )</H5>
@foreach($user->groups as $group)
    @if (array_key_exists($group->name, $managed_projects))
    {{Form::open(["action" => "\LaravelAcl\Authentication\Controllers\UserController@deleteGroup", "role"=>"form", 'name' => $group->id])}}
    <div class="form-group">
        <div class="input-group">
            <span class="input-group-addon form-button button-del-group" name="{{$group->id}}"><span class="glyphicon glyphicon-minus-sign add-input"></span></span>
            {{Form::text('group_name', $group->name, ['class' => 'form-control', 'readonly' => 'readonly'])}}
            {{Form::hidden('id', $user->id)}}
            {{Form::hidden('group_id', $group->id)}}
        </div>
    </div>
    {{Form::close()}}
    @endif
@endforeach
@elseif($user->exists)
    <span class="text-warning"><h5>There is no projects associated to the user.</h5></span>
@endif

@section('footer_scripts')
@parent
<script>
    $(".button-add-group").click( function(){
        $("#loading").css("display","block");
        <?php if($user->exists): ?>
        $('.form-add-group').submit();
        <?php endif; ?>
    });
    $(".button-del-group").click( function(){
        $("#loading").css("display","block");
        name = $(this).attr('name');
        $('form[name='+name+']').submit();
    });
</script>
@stop