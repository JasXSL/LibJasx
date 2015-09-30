<?php
	header('Content-Type: application/javascript');
	
	$CSRF = '';
	if(isset($_GET['CSRF_TOKEN']))$CSRF = $_GET['CSRF_TOKEN'];
	
?>
// JavaScript Document
// function callback((obj)this){}
function Ajax(task, data, form, callback, overrideErrors){
	this.task = task;
    this.data = data;
	this.form = form;
    this.success = false;
	this.response = null;
    this.url = Ajax.url;
    this.overrideErrors = overrideErrors;
    
    if(window.FormData === undefined)console.log("Browser does not support file uploads.");
    else{
    	if(!this.form instanceof FormData)
    		this.form = new FormData($(this.form)[0]);
    }
	
    
	var me = this;
	
    $.ajax({
		url:Ajax.url+"?t="+encodeURIComponent(me.task)+"&d="+encodeURIComponent(JSON.stringify(data))<?php if(!empty($CSRF))echo "+'&csrf=".urlencode($CSRF)."'";?>,
		data:form,
        cache:false,
        contentType:false,
        processData:false,
        type:'POST'
	}).always(function(d){
		me.response = d;
        //console.log(d);
		if(d.hasOwnProperty("succ")){
        	//console.log(d);
            
            
            if(Ajax.overrideAllErrors !== null)Ajax.overrideAllErrors(d.err, d.note);
            else if(me.overrideErrors !== undefined){
            	overrideErrors(d.err, d.note);
            }
            else{
               	if(d.err.length)addErrors(d.err.join('<br />'), true);
                if(d.note.length)addErrors(d.note.join('<br />'), false);
                if(d.err.length || d.note.length)$("html, body").animate({ scrollTop: 0 }, "slow");
			}
            
            if(d.redir)window.location = d.redir;
            
            me.response = d.vars;
			if(d.succ)me.success = true;
		}else console.log("Ajax failure", me);
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
Ajax.overrideAllErrors = null;

Ajax.setConf = function(url, onSuccessFunctions){
	Ajax.url = url;
    if(onSuccessFunctions !== undefined)Ajax.onSuccessFunctions = onSuccessFunctions;
}
Ajax.bindOnSuccessFunction = function(fn){
	Ajax.onSuccessFunctions.push(fn);
}
Ajax.bindErrorOverride = function(fn){
	Ajax.overrideAllErrors = fn;
}

<?php

	session_write_close();

?>