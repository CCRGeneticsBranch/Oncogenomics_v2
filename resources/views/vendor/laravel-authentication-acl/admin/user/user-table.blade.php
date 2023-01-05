<div class="panel panel-info">
    <div class="panel-heading">
        <h3 class="panel-title bariol-thin"><i class="fa fa-user"></i> {{Request::all() ? 'Search results:' : 'Users'}}</h3>
    </div>
    <div class="panel-body">
        
      <div class="row">
          <div class="col-md-12">
              @if(! $users->isEmpty() )
              <table id="user_table" class="table table-hover">
                      <thead>
                          <tr>
                              <th>ID</th>
                              <th>Email</th>
                              <th class="hidden-xs">First name</th>
                              <th class="hidden-xs">Last name</th>
                              <th class="hidden-xs">Permissions</th>
                              <!--th>Active</th-->
                              <th class="hidden-xs">Last login</th>
                              <th>Operations</th>
                          </tr>
                      </thead>
                      <tbody>
                          @foreach($users as $user)
                          <tr>
                              <td style="padding:0px">{!!$user->id!!}</td>
                              <td style="padding:0px">{!!$user->email_address!!}</td>
                              <td style="padding:0px" class="hidden-xs">{!!$user->first_name!!}</td>
                              <td style="padding:0px" class="hidden-xs">{!!$user->last_name!!}</td>
                              <td style="padding:0px" class="hidden-xs">{!!$user->permissions!!}</td>
                              <!--td style="padding:0px" >{{$user->activated ? '<i class="fa fa-circle green"></i>' : '<i class="fa fa-circle-o red"></i>'}}</td-->
                              <td style="padding:0px" class="hidden-xs">{{$user->last_login ? $user->last_login : 'not logged yet.'}}</td>
                              <td style="padding:0px" >
                                  @if(! $user->protected)
                                      <a href="{!!URL::action('\LaravelAcl\Authentication\Controllers\UserController@editUser', ['id' => $user->id])!!}"><i class="fa fa-pencil-square-o fa-2x"></i></a>
                                      <a href="{!!URL::action('\LaravelAcl\Authentication\Controllers\UserController@deleteUser',['id' => $user->id, '_token' => csrf_token()])!!}" class="margin-left-5 delete"><i class="fa fa-trash-o fa-2x"></i></a>
                                  @else
                                      <i class="fa fa-times fa-2x light-blue"></i>
                                      <i class="fa fa-times fa-2x margin-left-12 light-blue"></i>
                                  @endif
                              </td>
                          </tr>
                          @endforeach
                      </tbody>                      
              </table>              
              @else
                  <span class="text-warning"><h5>No results found.</h5></span>
              @endif
          </div>
      </div>
    </div>
</div>
