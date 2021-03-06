function BlorgLastFmGadget(id, username, limit, invert, date_format)
{
	this.id          = id;
	this.username    = username;
	this.limit       = limit;
	this.invert      = invert;
	this.output_div  = document.getElementById(this.id);
	this.date_format = (date_format === 'short') ? 'short' : 'long';

	this.init();
	this.requestRecentSongs();
}

BlorgLastFmGadget.throbber_text = 'loading …';
BlorgLastFmGadget.visit_text    = 'Visit the Last.fm page for this track';
BlorgLastFmGadget.none_text     = '‹none›';

BlorgLastFmGadget.months = [
	'January',
	'February',
	'March',
	'April',
	'May',
	'June',
	'July',
	'August',
	'September',
	'October',
	'November',
	'December'
];

BlorgLastFmGadget.short_months = [
	'Jan', 'Feb', 'Mar',
	'Apr', 'May', 'Jun',
	'Jul', 'Aug', 'Sep',
	'Oct', 'Nov', 'Dec'
];

BlorgLastFmGadget.throbber_image = new Image();
BlorgLastFmGadget.throbber_image.src =
	'packages/blorg/images/blorg-throbber.gif';

BlorgLastFmGadget.inverted_throbber_image = new Image();
BlorgLastFmGadget.inverted_throbber_image.src =
	'packages/blorg/images/blorg-inverted-throbber.gif';

BlorgLastFmGadget.prototype.init = function()
{
	var img_element = document.createElement('img');

	img_element.src    = (this.invert) ?
		BlorgLastFmGadget.inverted_throbber_image.src :
		BlorgLastFmGadget.throbber_image.src;

	img_element.width  = 20;
	img_element.height = 20;
	img_element.alt    = BlorgLastFmGadget.throbber_text;
	img_element.style.verticalAlign = 'middle';

	this.output_div.appendChild(img_element);
}

BlorgLastFmGadget.prototype.requestRecentSongs = function()
{
	var uri = 'ajax/last.fm/' + this.username;

	// looks for base href
	var bases = document.getElementsByTagName('base');
	if (bases.length > 0 && bases[0].href) {
		var base = bases[0].href;
		if (base.charAt(base.length - 1) != '/') {
			base += '/';
		}
		if (uri.charAt(0) == '/') {
			uri = uri.substring(1);
		}
		uri = base + uri;
	}

	var callback = {
		success: this.handleSuccessfulUpdate,
		failure: this.handleFailedUpdate,
		scope:   this
	};

	YAHOO.util.Connect.asyncRequest('GET', uri, callback);
}

BlorgLastFmGadget.prototype.handleSuccessfulUpdate = function(o)
{
	var doc = o.responseXML;

	while (this.output_div.firstChild) {
		this.output_div.removeChild(this.output_div.firstChild);
	}

	if (!doc) {
		var message = document.createElement('div');
		var message_div = document.createElement('div');
		var error_div = document.createElement('div');

		message.className = 'swat-message swat-message-error';
		message_div.className = 'swat-form-field-messages';
		error_div.className = 'swat-form-field-with-messages';

		message.appendChild(document.createTextNode(o.responseText));
		message_div.appendChild(message);
		error_div.appendChild(message_div);

		this.output_div.appendChild(error_div);
		return;
	}

	var tracks = doc.getElementsByTagName('track');

	if (tracks.length == 0) {
		var span = document.createElement('span');
		span.className = 'blorg-last-fm-gadget-none';
		span.appendChild(document.createTextNode(BlorgLastFmGadget.none_text));
		this.output_div.appendChild(span);
	} else {

		var ul = document.createElement('ul');
		YAHOO.util.Dom.setStyle(ul, 'opacity', 0);

		for (var i = 0; i < tracks.length && i < this.limit; i++) {
			var li = document.createElement('li');

			var artists = tracks[i].getElementsByTagName('artist');
			var names = tracks[i].getElementsByTagName('name');
			var urls = tracks[i].getElementsByTagName('url');
			var dates = tracks[i].getElementsByTagName('date');

			var date = new Date();
			date.setTime(parseInt(dates[0].getAttribute('uts')) * 1000);

			var a = document.createElement('a');
			a.href = urls[0].firstChild.nodeValue;
			a.title = BlorgLastFmGadget.visit_text;
			a.appendChild(document.createTextNode(
				artists[0].firstChild.nodeValue + ' — ' +
				names[0].firstChild.nodeValue));

			var span = document.createElement('span');
			span.appendChild(document.createTextNode(
				this.formatDate(date, this.date_format)));

			li.appendChild(a);
			li.appendChild(document.createElement('br'));
			li.appendChild(span);

			ul.appendChild(li);
		}

		this.output_div.appendChild(ul);

		var animation = new YAHOO.util.Anim(ul, { opacity: { from: 0, to: 1 } },
			0.5, YAHOO.util.Easing.easeIn);

		animation.animate();
	}
}

BlorgLastFmGadget.prototype.handleFailedUpdate = function(o)
{
}

BlorgLastFmGadget.prototype.formatDate = function(date, format)
{
	var hours = date.getHours();

	if (hours > 12) {
		hours -= 12;
		var ampm = 'pm';
	} else {
		var ampm = 'am';
	}
	if (hours == 0)
		hours = 12;

	var minutes = date.getMinutes();
	if (minutes < 10)
		minutes = '0' + minutes;

	if (format === 'short') {
		var formatted_date = hours + ':' + minutes + ampm + ', ' +
			BlorgLastFmGadget.short_months[date.getMonth()] + ' ' +
			date.getDate();
	} else {
		var formatted_date = hours + ':' + minutes + ' ' + ampm + ', ' +
			BlorgLastFmGadget.months[date.getMonth()] + ' ' + date.getDate();
	}

	return formatted_date;
}
