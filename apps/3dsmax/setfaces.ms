fn splitObject startframe maxframes suffix pass = (
	t = startframe
	arr = #()
	for i = t to (t+maxframes-1) do(
		sliderTime = i
		newobj = snapshot selection[1]
		convertTo newobj Editable_Poly
		append arr newobj.name
	)

	a = 0
	sort arr

	for i = 1 to arr.count do(
		obj = getnodebyname arr[i]
		if obj != undefined then(
			a+=1
			obj.EditablePoly.SetSelection #Face #{1..(obj.GetNumFaces()) }
			polyOp.setFaceMatID obj (polyOp.GetFaceSelection obj) a
		)
		else print i
	)

	r = getnodebyname arr[1]
	if r == undefined then(
		print "Fail because root"
	)
	else(
		for i = 2 to arr.count do(
			obj = getnodebyname arr[i]
			if obj != undefined then(
				polyOp.attach r obj
			)
			else print i
		)
		r.name = suffix + (pass as string)
	)
)

rollout SLANIM "Create SL mesh anim"
(
	edittext nrFrames "Nr Frames" text:"8"  labelontop:true
	edittext suffix "Name" text:"AnimPrim"  labelontop:true
	button goButton "Create SL Anim"
	on goButton pressed do(
		if selection.count == 0 then messagebox "Select an object first"
		else (
			if (nrFrames.text as integer) < 1 do nrFrames.text = "1"
			start = sliderTime; 
			end = nrFrames.text as integer;
			
			mf = (animationRange.end as string);
			mf = (substring mf 1 (mf.count-1)) as integer
			mf -=(start-1)
				
			if end > mf do(
				end = mf
				nrFrames.text = (end as string)
			)
			
			for i = 1 to ceil(end as float/8) do(
				subStart = start+((i-1)*8)
				nf = 8
				if subStart+nf>animationRange.end do nf = animationRange.end-subStart+1
				splitObject subStart nf suffix.text (i as integer)
			)
			sliderTime = start
		)
	)
)
createDialog SLANIM 150 120

/*
a = 0
for i in selection do
(	
	a+=1
	i.EditablePoly.SetSelection #Face #{1..(i.GetNumFaces()) }
	polyOp.setFaceMatID i (polyOp.GetFaceSelection i) a
)
*/