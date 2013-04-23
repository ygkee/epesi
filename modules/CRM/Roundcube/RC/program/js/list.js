function rcube_list_widget(a,b){this.ENTER_KEY=13;this.DELETE_KEY=46;this.BACKSPACE_KEY=8;this.list=a?a:null;this.frame=null;this.rows=[];this.selection=[];this.colcount=this.rowcount=0;this.subject_col=-1;this.modkey=0;this.col_drag_active=this.drag_active=this.dont_select=this.toggleselect=this.keyboard=this.column_movable=this.draggable=this.multi_selecting=this.multiexpand=this.multiselect=!1;this.column_fixed=null;this.shift_start=this.last_selected=0;this.focused=this.in_selection_before=!1;
this.drag_mouse_start=null;this.dblclick_time=600;this.row_init=function(){};if(b&&"object"===typeof b)for(var c in b)this[c]=b[c]}
rcube_list_widget.prototype={init:function(){if(this.list&&this.list.tBodies[0]){this.rows=[];this.rowcount=0;var a,b,c=this.list.tBodies[0].rows;a=0;for(b=c.length;a<b;a++)this.init_row(c[a]),this.rowcount++;this.init_header();this.frame=this.list.parentNode;this.keyboard&&(rcube_event.add_listener({event:bw.opera?"keypress":"keydown",object:this,method:"key_press"}),rcube_event.add_listener({event:"keydown",object:this,method:"key_down"}))}},init_row:function(a){if(a&&String(a.id).match(/^rcmrow([a-z0-9\-_=\+\/]+)/i)){var b=
this,c=RegExp.$1;a.uid=c;this.rows[c]={uid:c,id:a.id,obj:a};a.onmousedown=function(a){return b.drag_row(a,this.uid)};a.onmouseup=function(a){return b.click_row(a,this.uid)};if(bw.iphone||bw.ipad)a.addEventListener("touchstart",function(a){1==a.touches.length&&(b.drag_row(rcube_event.touchevent(a.touches[0]),this.uid)||a.preventDefault())},!1),a.addEventListener("touchend",function(a){1==a.changedTouches.length&&(b.click_row(rcube_event.touchevent(a.changedTouches[0]),this.uid)||a.preventDefault())},
!1);document.all&&(a.onselectstart=function(){return!1});this.row_init(this.rows[c])}},init_header:function(){if(this.list&&this.list.tHead){this.colcount=0;var a,b,c=this;if(this.column_movable&&this.list.tHead&&this.list.tHead.rows)for(b=0;b<this.list.tHead.rows[0].cells.length;b++)this.column_fixed!=b&&(a=this.list.tHead.rows[0].cells[b],a.onmousedown=function(a){return c.drag_column(a,this)},this.colcount++)}},clear:function(a){var b=document.createElement("tbody");this.list.insertBefore(b,this.list.tBodies[0]);
this.list.removeChild(this.list.tBodies[1]);this.rows=[];this.rowcount=0;a&&this.clear_selection();this.frame&&(this.frame.scrollTop=0)},remove_row:function(a,b){var c=this.rows[a]?this.rows[a].obj:null;c&&(c.style.display="none",b&&this.select_next(),delete this.rows[a],this.rowcount--)},insert_row:function(a,b){var c=this.list.tBodies[0];b&&c.rows.length?c.insertBefore(a,c.firstChild):c.appendChild(a);this.init_row(a);this.rowcount++},focus:function(a){var b,c;this.focused=!0;for(b in this.selection)c=
this.selection[b],this.rows[c]&&this.rows[c].obj&&$(this.rows[c].obj).addClass("selected").removeClass("unfocused");$(":focus:not(body)").blur();$("iframe").each(function(){this.blur()});(a||(a=window.event))&&rcube_event.cancel(a)},blur:function(){var a,b;this.focused=!1;for(a in this.selection)b=this.selection[a],this.rows[b]&&this.rows[b].obj&&$(this.rows[b].obj).removeClass("selected focused").addClass("unfocused")},drag_column:function(a,b){if(1<this.colcount){this.drag_start=!0;this.drag_mouse_start=
rcube_event.get_mouse_pos(a);rcube_event.add_listener({event:"mousemove",object:this,method:"column_drag_mouse_move"});rcube_event.add_listener({event:"mouseup",object:this,method:"column_drag_mouse_up"});this.add_dragfix();for(var c=0;c<this.list.tHead.rows[0].cells.length;c++)if(b==this.list.tHead.rows[0].cells[c]){this.selected_column=c;break}}return!1},drag_row:function(a,b){var c=rcube_event.get_target(a),d=c.tagName.toLowerCase();if(this.dont_select||c&&("input"==d||"img"==d)||2==rcube_event.get_button(a))return!0;
this.in_selection_before=this.in_selection(b)?b:!1;this.in_selection_before||(c=rcube_event.get_modifier(a),this.select_row(b,c,!1));if(this.draggable&&this.selection.length){this.drag_start=!0;this.drag_mouse_start=rcube_event.get_mouse_pos(a);rcube_event.add_listener({event:"mousemove",object:this,method:"drag_mouse_move"});rcube_event.add_listener({event:"mouseup",object:this,method:"drag_mouse_up"});if(bw.iphone||bw.ipad)rcube_event.add_listener({event:"touchmove",object:this,method:"drag_mouse_move"}),
rcube_event.add_listener({event:"touchend",object:this,method:"drag_mouse_up"});this.add_dragfix()}return!1},click_row:function(a,b){var c=(new Date).getTime(),d=rcube_event.get_modifier(a),e=rcube_event.get_target(a),f=e.tagName.toLowerCase();if(e&&("input"==f||"img"==f))return!0;if(this.dont_select)return this.dont_select=!1;e=c-this.rows[b].clicked<this.dblclick_time;!this.drag_active&&(this.in_selection_before==b&&!e)&&this.select_row(b,d,!1);this.in_selection_before=this.drag_start=!1;this.rows&&
e&&this.in_selection(b)?(this.triggerEvent("dblclick"),c=0):this.triggerEvent("click");this.drag_active||(this.del_dragfix(),rcube_event.cancel(a));this.rows[b].clicked=c;return!1},find_root:function(a){var b=this.rows[a];return b&&b.parent_uid?this.find_root(b.parent_uid):a},expand_row:function(a,b){var c=this.rows[b],d=rcube_event.get_target(a),e=rcube_event.get_modifier(a);this.dont_select=!0;c.clicked=0;c.expanded?(d.className="collapsed",e==CONTROL_KEY||this.multiexpand?this.collapse_all(c):
this.collapse(c)):(d.className="expanded",e==CONTROL_KEY||this.multiexpand?this.expand_all(c):this.expand(c))},collapse:function(a){a.expanded=!1;this.triggerEvent("expandcollapse",{uid:a.uid,expanded:a.expanded,obj:a.obj});for(var b=a.depth,a=a?a.obj.nextSibling:null,c;a;){if(1==a.nodeType){if((c=this.rows[a.uid])&&c.depth<=b)break;$(a).css("display","none");c.expanded&&(c.expanded=!1,this.triggerEvent("expandcollapse",{uid:c.uid,expanded:c.expanded,obj:a}))}a=a.nextSibling}return!1},expand:function(a){var b,
c,d,e,f;a?(a.expanded=!0,d=a.depth,e=a.obj.nextSibling,this.update_expando(a.uid,!0),this.triggerEvent("expandcollapse",{uid:a.uid,expanded:a.expanded,obj:a.obj})):(e=this.list.tBodies[0].firstChild,f=d=0);for(;e;){if(1==e.nodeType&&(b=this.rows[e.uid])){if(a&&(!b.depth||b.depth<=d))break;if(b.parent_uid)if((c=this.rows[b.parent_uid])&&c.expanded){if(a&&c==a||f>=c.depth-1)f=c.depth,$(e).css("display",""),b.expanded=!0,this.triggerEvent("expandcollapse",{uid:b.uid,expanded:b.expanded,obj:e})}else if(a&&
(!c||c.depth<=d))break}e=e.nextSibling}return!1},collapse_all:function(a){var b,c,d;if(a){if(a.expanded=!1,b=a.depth,c=a.obj.nextSibling,this.update_expando(a.uid),this.triggerEvent("expandcollapse",{uid:a.uid,expanded:a.expanded,obj:a.obj}),b&&this.multiexpand)return!1}else c=this.list.tBodies[0].firstChild,b=0;for(;c;){if(1==c.nodeType&&(d=this.rows[c.uid])){if(a&&(!d.depth||d.depth<=b))break;(a||d.depth)&&$(c).css("display","none");d.has_children&&d.expanded&&(d.expanded=!1,this.update_expando(d.uid,
!1),this.triggerEvent("expandcollapse",{uid:d.uid,expanded:d.expanded,obj:c}))}c=c.nextSibling}return!1},expand_all:function(a){var b,c,d;a?(a.expanded=!0,b=a.depth,c=a.obj.nextSibling,this.update_expando(a.uid,!0),this.triggerEvent("expandcollapse",{uid:a.uid,expanded:a.expanded,obj:a.obj})):(c=this.list.tBodies[0].firstChild,b=0);for(;c;){if(1==c.nodeType&&(d=this.rows[c.uid])){if(a&&d.depth<=b)break;$(c).css("display","");d.has_children&&!d.expanded&&(d.expanded=!0,this.update_expando(d.uid,!0),
this.triggerEvent("expandcollapse",{uid:d.uid,expanded:d.expanded,obj:c}))}c=c.nextSibling}return!1},update_expando:function(a,b){var c=document.getElementById("rcmexpando"+a);c&&(c.className=b?"expanded":"collapsed")},get_next_row:function(){if(!this.rows)return!1;for(var a=this.rows[this.last_selected],a=a?a.obj.nextSibling:null;a&&(1!=a.nodeType||"none"==a.style.display);)a=a.nextSibling;return a},get_prev_row:function(){if(!this.rows)return!1;for(var a=this.rows[this.last_selected],a=a?a.obj.previousSibling:
null;a&&(1!=a.nodeType||"none"==a.style.display);)a=a.previousSibling;return a},get_first_row:function(){if(this.rowcount){var a,b,c=this.list.tBodies[0].rows;a=0;for(b=c.length-1;a<b;a++)if(c[a].id&&String(c[a].id).match(/^rcmrow([a-z0-9\-_=\+\/]+)/i)&&null!=this.rows[RegExp.$1])return RegExp.$1}return null},get_last_row:function(){if(this.rowcount){var a,b=this.list.tBodies[0].rows;for(a=b.length-1;0<=a;a--)if(b[a].id&&String(b[a].id).match(/^rcmrow([a-z0-9\-_=\+\/]+)/i)&&null!=this.rows[RegExp.$1])return RegExp.$1}return null},
select_row:function(a,b,c){var d=this.selection.join(",");this.multiselect||(b=0);this.shift_start||(this.shift_start=a);if(b){switch(b){case SHIFT_KEY:this.shift_select(a,!1);break;case CONTROL_KEY:c||this.highlight_row(a,!0);break;case CONTROL_SHIFT_KEY:this.shift_select(a,!0);break;default:this.highlight_row(a,!1)}this.multi_selecting=!0}else this.shift_start=a,this.highlight_row(a,!1),this.multi_selecting=!1;this.selection.join(",")!=d&&this.triggerEvent("select");0!=this.last_selected&&this.rows[this.last_selected]&&
$(this.rows[this.last_selected].obj).removeClass("focused");this.toggleselect&&this.last_selected==a?(this.clear_selection(),a=null):$(this.rows[a].obj).addClass("focused");this.selection.length||(this.shift_start=null);this.last_selected=a},select:function(a){this.select_row(a,!1);this.scrollto(a)},select_next:function(){var a=this.get_next_row(),b=this.get_prev_row();(a=a?a:b)&&this.select_row(a.uid,!1,!1)},select_first:function(a){var b=this.get_first_row();b&&(a?(this.shift_select(b,a),this.triggerEvent("select"),
this.scrollto(b)):this.select(b))},select_last:function(a){var b=this.get_last_row();b&&(a?(this.shift_select(b,a),this.triggerEvent("select"),this.scrollto(b)):this.select(b))},select_childs:function(a){if(this.rows[a]&&this.rows[a].has_children)for(var b=this.rows[a].depth,a=this.rows[a].obj.nextSibling;a;){if(1==a.nodeType&&(r=this.rows[a.uid])){if(!r.depth||r.depth<=b)break;this.in_selection(r.uid)||this.select_row(r.uid,CONTROL_KEY)}a=a.nextSibling}},shift_select:function(a,b){if(!this.rows[this.shift_start]||
!this.selection.length)this.shift_start=a;var c,d=this.rows[this.shift_start].obj.rowIndex,e=this.rows[a].obj.rowIndex,f=d<e?d:e,d=d>e?d:e;for(c in this.rows)this.rows[c].obj.rowIndex>=f&&this.rows[c].obj.rowIndex<=d?this.in_selection(c)||this.highlight_row(c,!0):this.in_selection(c)&&!b&&this.highlight_row(c,!0)},in_selection:function(a){for(var b in this.selection)if(this.selection[b]==a)return!0;return!1},select_all:function(a){if(!this.rows||!this.rows.length)return!1;var b,c=this.selection.join(",");
this.selection=[];for(b in this.rows)!a||!0==this.rows[b][a]?(this.last_selected=b,this.highlight_row(b,!0)):$(this.rows[b].obj).removeClass("selected").removeClass("unfocused");this.selection.join(",")!=c&&this.triggerEvent("select");this.focus();return!0},invert_selection:function(){if(!this.rows||!this.rows.length)return!1;var a,b=this.selection.join(",");for(a in this.rows)this.highlight_row(a,!0);this.selection.join(",")!=b&&this.triggerEvent("select");this.focus();return!0},clear_selection:function(a){var b,
c=this.selection.length;if(a)for(b in this.selection){if(this.selection[b]==a){this.selection.splice(b,1);break}}else{for(b in this.selection)this.rows[this.selection[b]]&&$(this.rows[this.selection[b]].obj).removeClass("selected").removeClass("unfocused");this.selection=[]}c&&!this.selection.length&&this.triggerEvent("select")},get_selection:function(){return this.selection},get_single_selection:function(){return 1==this.selection.length?this.selection[0]:null},highlight_row:function(a,b){if(this.rows[a]&&
!b){if(1<this.selection.length||!this.in_selection(a))this.clear_selection(),this.selection[0]=a,$(this.rows[a].obj).addClass("selected")}else if(this.rows[a])if(this.in_selection(a)){var c=$.inArray(a,this.selection),d=this.selection.slice(0,c),c=this.selection.slice(c+1,this.selection.length);this.selection=d.concat(c);$(this.rows[a].obj).removeClass("selected").removeClass("unfocused")}else this.selection[this.selection.length]=a,$(this.rows[a].obj).addClass("selected")},key_press:function(a){var b=
a.target||{};if(!0!=this.focused||"INPUT"==b.nodeName||"TEXTAREA"==b.nodeName||"SELECT"==b.nodeName)return!0;var b=rcube_event.get_keycode(a),c=rcube_event.get_modifier(a);switch(b){case 40:case 38:case 63233:case 63232:return rcube_event.cancel(a),this.use_arrow_key(b,c);case 61:case 107:case 109:case 32:return rcube_event.cancel(a),a=this.use_plusminus_key(b,c),this.key_pressed=b,this.modkey=c,this.triggerEvent("keypress"),this.modkey=0,a;case 36:return this.select_first(c),rcube_event.cancel(a);
case 35:return this.select_last(c),rcube_event.cancel(a);default:if(this.key_pressed=b,this.modkey=c,this.triggerEvent("keypress"),this.modkey=0,this.key_pressed==this.BACKSPACE_KEY)return rcube_event.cancel(a)}return!0},key_down:function(a){var b=a.target||{};if(!0!=this.focused||"INPUT"==b.nodeName||"TEXTAREA"==b.nodeName||"SELECT"==b.nodeName)return!0;switch(rcube_event.get_keycode(a)){case 27:if(this.drag_active)return this.drag_mouse_up(a);if(this.col_drag_active)return this.selected_column=
null,this.column_drag_mouse_up(a);case 40:case 38:case 63233:case 63232:case 61:case 107:case 109:case 32:if(!rcube_event.get_modifier(a)&&this.focused)return rcube_event.cancel(a)}return!0},use_arrow_key:function(a,b){var c;if(40==a||63233==a)c=this.get_next_row();else if(38==a||63232==a)c=this.get_prev_row();c&&(this.select_row(c.uid,b,!1),this.scrollto(c.uid));return!1},use_plusminus_key:function(a,b){var c=this.rows[this.last_selected];if(c)return 32==a&&(a=c.expanded?109:61),61==a||107==a?b==
CONTROL_KEY||this.multiexpand?this.expand_all(c):this.expand(c):b==CONTROL_KEY||this.multiexpand?this.collapse_all(c):this.collapse(c),this.update_expando(c.uid,c.expanded),!1},scrollto:function(a){var b=this.rows[a].obj;if(b&&this.frame){var c=Number(b.offsetTop);!c&&this.rows[a].parent_uid&&(a=this.find_root(this.rows[a].uid),this.expand_all(this.rows[a]),c=Number(b.offsetTop));c<Number(this.frame.scrollTop)?this.frame.scrollTop=c:c+Number(b.offsetHeight)>Number(this.frame.scrollTop)+Number(this.frame.offsetHeight)&&
(this.frame.scrollTop=c+Number(b.offsetHeight)-Number(this.frame.offsetHeight))}},drag_mouse_move:function(a){if("touchmove"==a.type)if(1==a.changedTouches.length)a=rcube_event.touchevent(a.changedTouches[0]);else return rcube_event.cancel(a);if(this.drag_start){var b=rcube_event.get_mouse_pos(a);if(!this.drag_mouse_start||3>Math.abs(b.x-this.drag_mouse_start.x)&&3>Math.abs(b.y-this.drag_mouse_start.y))return!1;this.draglayer||(this.draglayer=$("<div>").attr("id","rcmdraglayer").css({position:"absolute",
display:"none","z-index":2E3}).appendTo(document.body));var c,d,e=$.merge([],this.selection);for(c in e)d=e[c],this.rows[d].has_children&&!this.rows[d].expanded&&this.select_childs(d);this.draglayer.html("");for(c=0;c<this.selection.length;c++){if(12<c){this.draglayer.append("...");break}if(e=this.rows[this.selection[c]].obj)for(d=b=0;d<e.childNodes.length;d++)if("TD"==e.childNodes[d].nodeName){0==c&&(this.drag_start_pos=$(e.childNodes[d]).offset());if(0>this.subject_col||0<=this.subject_col&&this.subject_col==
b){for(var f,g,h=e.childNodes[d].childNodes,b=0;b<h.length;b++)if((g=e.childNodes[d].childNodes[b])&&(3==g.nodeType||"A"==g.nodeName))f=g;if(!f)break;d=$(f).text();d=$.trim(d);d=50<d.length?d.substring(0,50)+"...":d;d=$("<div>").text(d);this.draglayer.append(d);break}b++}}this.draglayer.show();this.drag_active=!0;this.triggerEvent("dragstart")}this.drag_active&&this.draglayer&&(c=rcube_event.get_mouse_pos(a),this.draglayer.css({left:c.x+20+"px",top:c.y-5+(bw.ie?document.documentElement.scrollTop:
0)+"px"}),this.triggerEvent("dragmove",a?a:window.event));return this.drag_start=!1},drag_mouse_up:function(a){document.onmousemove=null;if("touchend"==a.type&&1!=a.changedTouches.length)return rcube_event.cancel(a);this.draglayer&&this.draglayer.is(":visible")&&(this.drag_start_pos?this.draglayer.animate(this.drag_start_pos,300,"swing").hide(20):this.draglayer.hide());this.drag_active&&this.focus();this.drag_active=!1;rcube_event.remove_listener({event:"mousemove",object:this,method:"drag_mouse_move"});
rcube_event.remove_listener({event:"mouseup",object:this,method:"drag_mouse_up"});if(bw.iphone||bw.ipad)rcube_event.remove_listener({event:"touchmove",object:this,method:"drag_mouse_move"}),rcube_event.remove_listener({event:"touchend",object:this,method:"drag_mouse_up"});this.del_dragfix();this.triggerEvent("dragend");return rcube_event.cancel(a)},column_drag_mouse_move:function(a){if(this.drag_start){var b;b=rcube_event.get_mouse_pos(a);if(!this.drag_mouse_start||3>Math.abs(b.x-this.drag_mouse_start.x)&&
3>Math.abs(b.y-this.drag_mouse_start.y))return!1;if(!this.col_draglayer){b=$(this.list).offset();var c=this.list.tHead.rows[0].cells;this.col_draglayer=$("<div>").attr("id","rcmcoldraglayer").css(b).css({position:"absolute","z-index":2001,"background-color":"white",opacity:0.75,height:this.frame.offsetHeight-2+"px",width:this.frame.offsetWidth-2+"px"}).appendTo(document.body).append($("<div>").attr("id","rcmcolumnindicator").css({position:"absolute","border-right":"2px dotted #555","z-index":2002,
height:this.frame.offsetHeight-2+"px"}));this.cols=[];this.list_pos=this.list_min_pos=b.left;for(b=0;b<c.length;b++)this.cols[b]=c[b].offsetWidth,null!==this.column_fixed&&b<=this.column_fixed&&(this.list_min_pos+=this.cols[b])}this.col_draglayer.show();this.col_drag_active=!0;this.triggerEvent("column_dragstart")}if(this.col_drag_active&&this.col_draglayer){var c=0,d=rcube_event.get_mouse_pos(a);for(b=0;b<this.cols.length;b++)if(d.x>=this.cols[b]/2+this.list_pos+c)c+=this.cols[b];else break;0==b&&
this.list_min_pos>d.x?c=this.list_min_pos-this.list_pos:!this.list.rowcount&&b==this.cols.length&&(c-=2);$("#rcmcolumnindicator").css({width:c+"px"});this.triggerEvent("column_dragmove",a?a:window.event)}return this.drag_start=!1},column_drag_mouse_up:function(a){document.onmousemove=null;this.col_draglayer&&(this.col_draglayer.remove(),this.col_draglayer=null);this.col_drag_active&&this.focus();this.col_drag_active=!1;rcube_event.remove_listener({event:"mousemove",object:this,method:"column_drag_mouse_move"});
rcube_event.remove_listener({event:"mouseup",object:this,method:"column_drag_mouse_up"});this.del_dragfix();if(null!==this.selected_column&&this.cols&&this.cols.length){var b,c=0,d=rcube_event.get_mouse_pos(a);for(b=0;b<this.cols.length;b++)if(d.x>=this.cols[b]/2+this.list_pos+c)c+=this.cols[b];else break;b!=this.selected_column&&b!=this.selected_column+1&&this.column_replace(this.selected_column,b)}this.triggerEvent("column_dragend");return rcube_event.cancel(a)},add_dragfix:function(){$("iframe").each(function(){$('<div class="iframe-dragdrop-fix"></div>').css({background:"#fff",
width:this.offsetWidth+"px",height:this.offsetHeight+"px",position:"absolute",opacity:"0.001",zIndex:1E3}).css($(this).offset()).appendTo(document.body)})},del_dragfix:function(){$("div.iframe-dragdrop-fix").each(function(){this.parentNode.removeChild(this)})},column_replace:function(a,b){var c;c=this.list.tHead.rows[0].cells;var d=c[a],e=c[b],f=document.createElement("td");e?c[0].parentNode.insertBefore(f,e):c[0].parentNode.appendChild(f);c[0].parentNode.replaceChild(d,f);r=0;for(c=this.list.tBodies[0].rows.length;r<
c;r++)row=this.list.tBodies[0].rows[r],d=row.cells[a],e=row.cells[b],f=document.createElement("td"),e?row.insertBefore(f,e):row.appendChild(f),row.replaceChild(d,f);this.subject_col==a?this.subject_col=b>a?b-1:b:this.subject_col<a&&b<=this.subject_col?this.subject_col++:this.subject_col>a&&b>=this.subject_col&&this.subject_col--;this.triggerEvent("column_replace")}};rcube_list_widget.prototype.addEventListener=rcube_event_engine.prototype.addEventListener;
rcube_list_widget.prototype.removeEventListener=rcube_event_engine.prototype.removeEventListener;rcube_list_widget.prototype.triggerEvent=rcube_event_engine.prototype.triggerEvent;
