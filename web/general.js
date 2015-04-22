// JavaScript Document



// Generic listeners
/*
	data-href = link when clicked
	select[data-transport] = changed redir to data-transport+val
	input[data-imgpreview] = changed - updates an images src, use data-urlpre, data-urlpost
*/

function Tools(){}
Tools.debugOn = false;
Tools.debug = function(){
	if(!Tools.debugOn)return;
	console.log("General JS debug", arguments);
}

Tools.init = function(){
	$("[data-href]").unbind('click').click(function(){window.location=$(this).attr("data-href");});
	$("select[data-transport]").unbind('click').click(function(){
		if(parseInt($(this).val())<=0)return;
		window.location=$(this).attr("data-transport")+$(this).val();
	});
	
	
	function refreshImg(obj){
		var pre = $(obj).attr('data-urlpre');
		var post = $(obj).attr('data-urlpost');
		$($(obj).attr('data-imgpreview')).attr('src', pre+$(obj).val()+post);
	}
	$("input[data-imgpreview], select[data-imgpreview]").change(function(){refreshImg(this);});
	$("input[data-imgpreview], select[data-imgpreview]").each(function(){refreshImg(this)});
	
	
	
	function refreshLabel(obj){
		$(obj).parent().toggleClass('checked', $(obj).prop('checked'));
	}
	$("label input[type=checkbox]").unbind('change').change(function(evt){refreshLabel(this);});
	$("label input[type=checkbox]").unbind('click').click(function(evt){evt.stopImmediatePropagation();});
	$("label input[type=checkbox]").each(function(){refreshLabel(this);});
	
	
	// Overlay
	$('body').append('<div id="overlayBox" style="display:none;"><div id="overlayCenter"></div></div>');
	$('#overlayBox').click(function(evt){
		Tools.setOverlayBox('');
		if(typeof Inv !== 'undefined')Inv.rebind();
	});
	$('#overlayBox').children().click(function(evt){evt.stopImmediatePropagation();});
	
}
Tools.htmlspecialchars = function(text) {
  if(text === undefined || text === null)return '';
  var map = {
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#039;'
  };
  return text.replace(/[&<>"']/g, function(m) { return map[m]; });
}

Tools.alnum = function(text){
	return text.match(/^[\w\-\s]+$/);
}

Tools.olSubs = new Array;
Tools.setOverlayBox = function(data){
	
	if(data == ''){
		Tools.olSubs.pop();
		if(!Tools.olSubs.length){
			$('#overlayBox').fadeOut();
			return;
		}
	}
	else Tools.olSubs.push(data);
	
	
	$("#overlayCenter").html(Tools.olSubs[Tools.olSubs.length-1]);
	$("#overlayBox").fadeIn();
	
	if(typeof Inv !== 'undefined')Inv.rebind();
	if(typeof Craft !== 'undefined')Craft.rebind();
}



Tools.multiSplit = function(input, d){
    var delim = d.slice();
    var reserved = ['.','^','$','*','+','?','(',')','[','{','\\','|'];
    for(var i in delim){
        if(~reserved.indexOf(delim[i]))delim.splice(i, 1, '\\'+delim[i]);
    }

    return input.split(new RegExp("("+delim.join("|")+")", 'g'));
}

// Converts a mathematical string to a number
// Lets you use jquery sums through jq::btoa(attr::search)
// § is a reserved keyword
Tools.mathToFloat = function(al, additionalVars, part){
    var parts = [
        ['(', ')'],                          
        ['+'],                              
        ['*','/'],
        ['^'], 
        ["RAND","CEIL","FLOOR","ROUND"]
    ];
    
    if(typeof part === "undefined")part = 0;
    if(typeof additionalVars === "undefined")additionalVars = new Object;
    var val = 0;
    if(part==0)al = al.replace('+-','-').replace('*-', '*§').replace('/-', '/§').replace('^-', '^§').replace('-', '+-').replace('§','-');
    var split = Tools.multiSplit(al, parts[part]);
    if(part == 0){
        if(split.length>2){
            for(var i in split){
                var str = split[i].trim();
                if(str == "("){
                    var ps = 1; var out = new Array;
                    for(var x = Number(i)+1; x<split.length; x++){
                        if(split[x] == "(")ps++;
                        else if(split[x] == ")") ps--;
                        out.push(split[x]);
                        
                        if(ps == 0 || x>=split.length){
                            var run = out.slice(0, out.length-2).join("");
                            if(run != ""){
                            nr = Tools.mathToFloat(run,additionalVars, 0); 
                            split.splice(i, x-i+1, nr);
                            break;
                            }
                        }
                    }
                }
            }
        }
        part = 1;
        split = Tools.multiSplit(split.join(""), parts[part]);
		console.log("Running formula: ", split);
    }

    var action = "+";
    for(var i in split){
        var str = split[i].trim();
      
        if(~parts[part].indexOf(str))action = str;
        else{
            var v = 0;
            if(str == "")continue;
            
            // jQuery sum syntax: jq::attribute::jquery_selector
            // Note: jquery selector cannot use parentheses
            if(typeof additionalVars[str] != 'undefined')
                v = Number(additionalVars[str]);
            else if(part < parts.length-1){
                v = Tools.mathToFloat(str, additionalVars, part+1);
            }
            else if(str.substring(0, 4) == "jq::" || str.substring(1,5) == "jq::"){
				var neg = '';
				if(str.substring(0,1) == "-"){
					neg = '-'; str = str.substr(1);
				}
				
				try{
                	str = decodeURIComponent(atob(str.substring(4)));
				}catch(err){
					console.log("Unable to convert JQ algo to string", str.substring(4));
				}
                var spl = str.split('::');
				spl[0] = spl[0].replace("[", "");
				spl[0] = spl[0].replace("]", "");
				
                Tools.debug("Searching for ", spl[1], " and extracting ", spl[0], $(spl[1]));

                $(spl[1]).each(function(index, value){
					if(spl[0] == "value")v+=Number($(value).val());
					else v+=Number($(value).attr(spl[0]));
                });
				v = Number(neg+v);
            }
            else v = str;
            if(action == "+"){
                var spl = String(v).split("-");
                val+=Number(spl.shift());
                for(var x in spl)val-=Number(spl[x]);
            }
            else{
                v = Number(v);
                if(action == "*")val*=v;
                else if(action == "/")val/=v;
                else if(action == "^")val = Math.pow(val, v);
                else if(action == "RAND")val+=Math.random()*v;
                else if(action == "CEIL")val+=Math.ceil(v);
                else if(action == "FLOOR")val+=Math.floor(v);
                else if(action == "ROUND")val+=Math.round(v);
            }
        }
    }
    
    return val;
}






$(function(){
	Tools.init();
	$.fn.serializeObject = function()
	{
		var o = {};
		var a = this.serializeArray();
		$.each(a, function() {
			if (o[this.name] !== undefined) {
				if (!o[this.name].push) {
					o[this.name] = [o[this.name]];
				}
				o[this.name].push(this.value || '');
			} else {
				o[this.name] = this.value || '';
			}
		});
		return o;
	};
})


