/*
	
	Panda AJAX module compatible with jasx AjaxCore
	Does not need PHP but needs CSRF
	
	new Pajax() accepts url, csrf, onError
	If they are not set, globals will be used instead
	
	You can set up globals by running Pajax.set(key, value);
	List of settings and defaults:
	- CSRF : ''
	- URL : 'ajax.php'
	- onError: function((arr)err, (arr)notices){ if(err){console.log("Err", err);} if(notices){console.log("Notice", notices);}}
	- onSuccesses: [] | Array of additional callbacks to run when the call is complete. should be function(data){} with data being the returned data and this being the Cal object
	
	A difference to be noted is that the ajax callback is sent with this as the this.Call object, and the parameter being the returned data	
	
	
	You can make it backwards compatible by adding a function to the global scope like:
	var instance = new Pajax();
	Ajax = instance.Call;
	
	If you want to make many requests you can use promises!
	simply make a call like:
		var call = new Pajax.Call(someVars);
		call.promise.then(function(result){}, function(err){})
	
*/

// function callback((obj)this){}
function Pajax(url, csrf, onError, onSuccesses){
	"use strict";
	var parent = this;
	this.csrf = csrf !== undefined ? csrf : Pajax.CSRF;
	this.url = url !== undefined ? url : Pajax.URL;
	this.onError = onError !== undefined ? onError : Pajax.ONERROR;
	
	this.onSuccesses = onSuccesses !== undefined ? onSuccesses : Pajax.ONSUCCESSES;
	if(this.onSuccesses.constructor !== Array){
		this.onSuccesses = [this.onSuccesses];
	}
	
	this.Call = function(task, data, form, callback){
		var me = this;
		
		// Conf
		
		this.task = task;
		this.data = data;
		this.form = form ? form : new FormData();
		this.callback = callback;
		
		// If this is not a form data object, convert it to one by using jquery
		if(this.form.constructor !== FormData){
			this.form = new FormData($(form)[0]);
		}
		
		
		
		// Auto
		this.response = {};
		this.time = Date.now();		// Timestamp 
		this.success = false;
		this.parent = parent;
		this.promise = null;

		
		this.form.append("_AJAX_DATA", JSON.stringify(this.data));

		this.promise = new Promise(function(suc, fail){
			$.ajax({
				url:parent.url+"?t="+encodeURIComponent(me.task)+"&csrf="+parent.csrf,
				data:me.form,
				processData: false,
  				contentType: false,
				type:'POST'
			})
			.done(function(d){
				me.response = d;
				
				
				// Failed response
				if(!d.hasOwnProperty("succ")){console.log("Ajax SYNTAX ERROR", d); return;}
				
				// Successful response
				if(d.succ){me.success = true;}
				else{console.log("Ajax unsuccessful", me);}
					
				// Handle errors and notices
				if(d.err.length || d.note.length){
					parent.onError.call(me, d.err, d.note);
				}
				
				// Auto redirect
				if(d.redir){
					
					var url = d.redir;
					
					if(typeof url !== "string" && url.length>1){
						url = url[0];
						
						// Open in blank window
						if(url[1]){
							window.open(url, '_blank');
							return;
						}
					}
					
					window.location = d.redir;
					return;
				}
			})
			.fail(function(data){console.log("AJAX connection error: ", data);})
			.always(function(){
								
				var vars = {};
				if(me.response.vars){
					vars = me.response.vars;
				}
				
				// Always callback
				if(me.callback !== undefined){
					callback.call(me, vars);
				}
				
				// Run additional callbacks
				for(var i = 0; i<parent.onSuccesses.length; i++){
					parent.onSuccesses[i].call(me, vars);
				}
				
				suc(me);
			});
		});
	};
	
	// Binds an additional function to onSuccess
	this.bindOnSuccess = function(fn){
		this.onSuccesses.push(fn);
	};
	
} 

(function(){
	"use strict";
	// Defaults
	Pajax.CSRF = '';
	Pajax.URL = 'ajax.php';
	Pajax.ONERROR = function(err, notices){ 
		if(err){console.log("Err", err);} 
		if(notices){console.log("Notice", notices);}
	};
	Pajax.ONSUCCESSES = [];
	
	// Set values
	Pajax.set = function(k, v){
		k = k.toLowerCase();
		if(k === "csrf"){Pajax.CSRF = v;}
		else if(k === "url"){Pajax.URL = v;}
		else if(k === "onerror"){Pajax.ONERROR = v;}
		else if(k === "onsuccesses"){
			if(v.constructor === Array){Pajax.ONSUCCESSES.push.apply(Pajax.ONSUCCESSES, v);} // Array, append it
			else{Pajax.ONSUCCESSES.push(v);}
		}
	};

})();