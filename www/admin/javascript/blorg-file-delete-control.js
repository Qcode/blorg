function BlorgFileDeleteControl(id, file_id, file_title)
{
	this.file_id = file_id;
	this.file_title = file_title;

	this.xml_rpc_client = new XML_RPC_Client('Post/FileAjaxServer');


	this.anchor = document.createElement('a');
	this.anchor.href = '#';
	if (typeof this.anchor.textContent == 'undefined')
		this.anchor.innerText = BlorgFileDeleteControl.delete_text;
	else
		this.anchor.textContent = BlorgFileDeleteControl.delete_text;

	YAHOO.util.Event.on(this.anchor, 'click', this.handleClick, this, true);

	var span = document.getElementById(id);
	span.appendChild(this.anchor);

	this.div = span.parentNode.parentNode.parentNode;
}

BlorgFileDeleteControl.confirm_text = 'Delete the file “%s”?';
BlorgFileDeleteControl.delete_text  = 'Delete';

BlorgFileDeleteControl.prototype.handleClick = function(e)
{
	YAHOO.util.Event.preventDefault(e);

	var confirm_text = BlorgFileDeleteControl.confirm_text.replace(
		'%s', this.file_title);

	if (confirm(confirm_text))
		this.deleteFile();
}

BlorgFileDeleteControl.voidClickHandler = function(e)
{
	YAHOO.util.Event.preventDefault(e);
}

BlorgFileDeleteControl.prototype.deleteFile = function()
{
	YAHOO.util.Event.removeListener(this.anchor, 'click', this.handleClick);
	YAHOO.util.Event.on(this.anchor, 'click',
		BlorgFileDeleteControl.voidClickHandler);

	var that = this;
	function callBack(response)
	{
		that.fadeOut();
	}

	this.xml_rpc_client.callProcedure('delete', callBack,
		[this.file_id], ['int']);
}

BlorgFileDeleteControl.prototype.fadeOut = function()
{
	var attributes = { opacity: { to: 0 } };
	var animation = new YAHOO.util.Anim(this.div, attributes, 0.5,
		YAHOO.util.Easing.easeOut);

	animation.onComplete.subscribe(this.shrink, this, true);
	animation.animate();
}

BlorgFileDeleteControl.prototype.shrink = function()
{
	this.div.style.overflow = 'hidden';

	var attributes = { height: { to: 0 },
		marginTop: { to: 0 },
		marginBottom: { to: 0 } };

	var animation = new YAHOO.util.Anim(this.div, attributes, 0.25,
		YAHOO.util.Easing.easeOut);

	animation.onComplete.subscribe(
		function() { this.div.parentNode.removeChild(this.div); }, this, true);

	animation.animate();
}
