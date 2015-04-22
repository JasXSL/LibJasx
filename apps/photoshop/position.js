var doc = app.activeDocument;
//var width = parseInt(prompt("Enter the width of the images.","256","256"));
//var height = parseInt(prompt("Enter the width of the images.","64","64"));
var originalRulerUnits = preferences.rulerUnits;
preferences.rulerUnits = Units.PIXELS;
	
var width = doc.width.value;
var height = doc.height.value;
var framerate = 30;

//var framerate = parseInt(prompt("Enter a framerate [FPS] (optional)","25","25"));
if(width == 0)alert("Incorrect width specified");
else if(height == 0)alert("Incorrect height specified");
else{
	var len = doc.artLayers.length;
	var theX = len*width;
	if(theX>1024)theX = 1024;
	var theY = Math.ceil(width*(len-1)/theX)*height;
	
	var i = height;
	for(i = height; i<theY; i*=2){}
	theY = i;
	doc.resizeCanvas(theX+"px", theY+"px");
	
	//$.writeln("TheX: "+theX);
	
	function MoveLayerTo(fLayer,fX,fY) {
	  var Position = fLayer.bounds;
	  Position[0] = fX - Position[0];
	  Position[1] = fY - Position[1];
	  fLayer.translate(-Position[0],-Position[1]);
	}

	var x = 0;
	for(var layerIndex=0; layerIndex < len; layerIndex++) {
		var layer=doc.artLayers[layerIndex];
		if(layer != null){
			var p_y = Math.floor(width*x/theX);
			var p_x = width*x-(p_y*theX);
			MoveLayerTo(layer, p_x, p_y*height);
			x++;
			//$.writeln(x+" "+p_x+" "+p_y+":"+(height/2+(p_y*height)));
		}
	}
	prompt("And here's a quick function you can use","llSetTextureAnim(ANIM_ON|LOOP, 0, "+(theX/width)+", "+(theY/height)+", 0, 0, "+framerate+");","256");
	
	preferences.rulerUnits = originalRulerUnits;
	
}
