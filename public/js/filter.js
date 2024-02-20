function OncoFilter(filter_list, filter_settings, filter_func) {

    // INITIALIZATION

    this.filter_list = ["Select filter"].concat(filter_list.sort());
    this.filters = [];
    this.filter_settings = filter_settings;
    this.total_filter = 0;
    this.filter_idx = 0;    
    this.filter_func = filter_func;

    for (var i in this.filter_settings) {
        var filter_id = this.addFilter(false);
        for (var j in this.filter_settings[i]) {
            if (this.filter_settings[i][j].length == 1)
                this.addFilterElem(filter_id, this.filter_settings[i][j][0]);
            else
                this.addFilterElem(filter_id, this.filter_settings[i][j][0], this.filter_settings[i][j][1] == '&&');
        }
    }    
 }   

OncoFilter.prototype.addFilter = function(addElement=true) {
    
        if (this.total_filter >= this.filter_list.length)
            return;

        var filter_id = this.guid(); 
        var onco_filter = this;    
        var op = $('<span id="' + filter_id + '" class="rcorner">' +
                        '<button id="btnAddFilterElem' + filter_id + '">+</button>' +
                        '<a class="boxclose" id="boxclose' + filter_id + '"></a>' +
                        '</span>');
        $("#filter").append(op); 
        $("#btnAddFilterElem" + filter_id).on('click', function() {
            onco_filter.addFilterElem(filter_id);
        });
        $("#boxclose" + filter_id).on('click', function() {
            onco_filter.delFilter(filter_id);
        });                       
        this.filters[filter_id] = [];
        this.filter_idx++;
        this.total_filter++;
        if (addElement)
            this.addFilterElem(filter_id);
        return filter_id;       
}

OncoFilter.prototype.addFilterElem = function(id, selDefault, isChecked) {
        selDefault = selDefault || '';
        var onco_filter = this;
        if (this.filters[id].length >= this.filter_list.length)
            return;
        var elem_id = this.guid();
        var new_elem = '<select id="sel' + elem_id + '" class="varFilter filterElem"></select>';
        var checked_str = (isChecked)? "checked" : "";
        if (this.filters[id].length > 0)
            new_elem = '<input type="checkbox" id="ckOp' + elem_id + '" class="filterElem" ' + checked_str + ' data-off-color="warning" data-label-width="0" data-on-text="AND" data-off-text="OR" data-size="mini">' + new_elem;
        var op = $(new_elem);
        $("#" + id).append(op); 
        if (this.filters[id].length > 0) {
            $("#ckOp" + elem_id).bootstrapSwitch();  
            $('input[id="ckOp' + elem_id + '"]').on('switchChange.bootstrapSwitch', function(event, state) {
                onco_filter.filter_func();                
            })
        }
        $("#sel" + elem_id).on('change', function() {
            onco_filter.filter_func();            
        });
        var checked = false;   
        var filter_keys = [];

        for (var i in this.filter_list) {
            var key = this.filter_list[i];
            
            //find next key not used            
            if (selDefault != '') {
                if (key == selDefault)
                    $("#sel" + elem_id).append($('<option>', { value : key , selected: "selected"}).text(key));
                else
                    $("#sel" + elem_id).append($('<option>', { value : key}).text(key)); 
            } else
                $("#sel" + elem_id).append($('<option>', { value : key}).text(key)); 
        }
        this.filters[id].push(elem_id);
        return elem_id;
}

OncoFilter.prototype.clearFilter = function() {
    for (var id in this.filters) {
        delete this.filters[id];
        $("#" + id).remove();
    }
    this.total_filter = 0;
    this.filter_func();
}

OncoFilter.prototype.delFilter = function(id){
    delete this.filters[id];
    $("#" + id).remove();
    this.total_filter--;
    this.filter_func();
}

OncoFilter.prototype.guid = function() {
    function s4() {
        return Math.floor((1 + Math.random()) * 0x10000).toString(16).substring(1);
    }
    return s4() + s4() + '-' + s4() + '-' + s4() + '-' + s4() + '-' + s4() + s4() + s4();
}

OncoFilter.prototype.getFilterName = function(i, j) {
    return $('#sel' + this.filters[i][j]).val();
}

OncoFilter.prototype.hasFilterOperator = function(i, j) {
    return ($('#ckOp' + this.filters[i][j]).length > 0);
}

OncoFilter.prototype.getFilterOperator = function(i, j) {
    return $('#ckOp' + this.filters[i][j]).is(":checked");
}

