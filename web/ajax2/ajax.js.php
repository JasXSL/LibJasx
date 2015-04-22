<?php
	header('Content-Type: application/javascript');
	session_start();
	if(!isset($_SESSION['CSRF_TOKEN']))$_SESSION['CSRF_TOKEN'] = md5(rand(0, 0xFFFFFFF));
?>
// JavaScript Document
// function callback((obj)this){}
function Ajax(method, args, callback){
	this.task = task;
	this.args = args;
	this.success = false;
	this.response = null;
	
	var me = this;
	$.post(
		Ajax.url,
        'call='+encodeURIComponent(JSON.stringify([this.task].concat(this.args)))
		<?php 
			if(isset($_SESSION['CSRF_TOKEN']))echo "+'&csrf=".$_SESSION['CSRF_TOKEN']."'";
		?>
	).always(function(d){
		me.response = d;
        //console.log(d);
		if(d.hasOwnProperty("succ")){
        	//console.log(d);
            if(d.err.length)addErrors(d.err.join('<br />'), true);
			if(d.note.length)addErrors(d.note.join('<br />'), false);
			if(d.err.length || d.note.length)$("html, body").animate({ scrollTop: 0 }, "slow");
			if(d.redir)window.location = d.redir;
            
            me.response = d.vars;
			if(d.succ)me.success = true;
		}
        if(me !== undefined && callback !== undefined)callback(me);
        for(var i in Ajax.onSuccessFunctions)Ajax.onSuccessFunctions[i](me);
	}).fail(function(data){
		console.log("AJAX Fail: ");
		console.log(data);
	});
	
} 


// These let you tie project specific actions
Ajax.url = '/ajax.php';
Ajax.onSuccessFunctions = new Array;
Ajax.setConf = function(url, onSuccessFunctions){
	Ajax.url = url;
    if(onSuccessFunctions !== undefined)Ajax.onSuccessFunctions = onSuccessFunctions;
}
Ajax.bindOnSuccessFunction = function(fn){
	Ajax.onSuccessFunctions.push(fn);
}

