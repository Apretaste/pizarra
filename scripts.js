var colors = {
	'Azul':'#99F9FF', 'Verde':'#9ADB05',
	'Rojo':'#FF415B', 'Morado':'#58235E',
	'Naranja':'#F38200', 'Amarillo':'#FFE600'
};

var avatars = {
	'Rockera':'M', 'Tablista':'M', 'Rapero':'M', 'Guapo':'M', 'Bandido':'M', 'Encapuchado':'M', 'Rapear':'M', 'Inconformista':'M', 'Coqueta':'M',
	'Punk':'M', 'Metalero':'M', 'Rudo':'M', 'Señor':'M', 'Nerd':'M', 'Hombre':'M', 'Cresta':'M', 'Emo':'M', 'Fabulosa':'M', 'Mago':'M', 'Jefe':'M', 'Sensei':'M',
	'Rubia':'M', 'Dulce':'M', 'Belleza':'M', 'Músico':'M', 'Rap':'M', 'Artista':'M', 'Fuerte':'M', 'Punkie':'M', 'Vaquera':'M', 'Modelo':'M', 'Independiente':'M',
	'Extraña':'M', 'Hippie':'M', 'Chica Emo':'M', 'Jugadora':'M', 'Sencilla':'M', 'Geek':'M', 'Deportiva':'M', 'Moderna':'M', 'Surfista':'M', 'Señorita':'M',
	'Rock':'M', 'Genia':'M', 'Gótica':'M', 'Sencillo':'M', 'Hawaiano':'M', 'Ganadero':'M', 'Gótico':'M'
};


$(document).ready(function () {
	$('.fixed-action-btn').floatingActionButton();
	$('.modal').modal();
	$('select').formSelect();

	M.FloatingActionButton.init($('.click-to-toggle'), {
		direction: 'left',
		hoverEnabled: false
	});

	//For main profile
	if (typeof profile != "undefined" && typeof isMyOwnProfile != "undefined") {
		if (isMyOwnProfile) {
			$("#editar").click(function () {
				return apretaste.send({"command": "PERFIL EDITAR"})
			});
			$('.footer').addClass('hide');
		} else {
			$("#chat").click(function () {
				apretaste.send({
					"command": 'CHAT',
					data: {"username": profile.username}
				});
			});

			$("#bloquear").click(function () {
				apretaste.send({
					"command": 'PERFIL BLOQUEAR',
					data: {"username": profile.username}
				});
			});
		}
		$("#notas").click(function () {
			apretaste.send({
				"command": 'PIZARRA',
				data: {"search": '@' + profile.username}
			});
		});
	}

	if (typeof activeIcon != "undefined") {
		var menuItem = $($('.footer i')[activeIcon - 1]);
		menuItem.removeClass('grey-text');
		menuItem.removeClass('text-darken-3');
		menuItem.addClass('pizarra-color-text');
	}

	$(window).resize(() => resizeImg());

	if ($('.container:not(#writeModal) > .row').length == 3) {
		var h = $('.container:not(#writeModal) > .row')[2].clientHeight + 8;
		$('.fixed-action-btn').css('bottom', h + 'px');

		$('#writeModal .actions').css('bottom', (h-8)+'px');
	}

	$('#uploadPhoto').click((e) => loadFileToBase64());

	var resizeInterval = setInterval(function(){ // check until the img has the correct size
		resizeImg();
		if($('#profile-rounded-img').css('background-size') != 'auto') clearTimeout(resizeInterval);
	}, 1);

	if(typeof notes != "undefined" || typeof chats != "undefined") $('#searchButton').removeClass('hide');
	if(typeof chats != "undefined" || typeof chat != "undefined") $('#chatButton').addClass('hide');
	if(typeof populars != "undefined" || $('#colors-nav').length > 0){
		if($('.container > .row').length == 3) $('.container> .row:first-child').css('margin-bottom','0');
		else $('.container> .row:first-child').css('margin-bottom','15px');
	}

	$('#chat-row').parent().css('margin-bottom','0');
});

function resizeImg() {
	if (typeof profile == "undefined") return;

	if($('.container:not(#writeModal) > .row').length == 3) $('.container:not(#writeModal) > .row:first-child').css('margin-bottom','0');

	var img = $('#profile-rounded-img');
	var size = $(window).height() / 4; // picture must be 1/4 of the screen
	img.height(size);
	img.width(size);

	img.css('top', (-4 - $(window).height() / 8) + 'px'); // align the picture with the div
	$('#edit-fields').css('margin-top', (-10 - $(window).height() / 8) + 'px'); // move the row before to the top to fill the empty space
	$('#img-pre').height(img.height() * 0.8); // set the height of the colored div after the photo
}

function getAvatar(avatar, serviceImgPath, size) {
	var index = Object.keys(avatars).indexOf(avatar);
	var fullsize = size*7;
	var x = (index % 7)*size;
	var y = Math.floor(index/7)*size
	return "background-image: url("+serviceImgPath+"avatars.png);" +
		"background-size: "+fullsize+"px "+fullsize+"px;" +
		"background-position: -"+x+"px -"+y+"px;"
}

function getYears() {
	var year = new Date().getFullYear();
	var years = [];
	for (let i = year - 15; i >= year - 90; i--) years.push(i);
	return years;
}

function toggleWriteModal() {
	var status = $('#writeModal').attr('status');

	if (status == "closed") {
		if ($('.container:not(#writeModal) > .row').length == 3) {
			var h = $('.container:not(#writeModal) > .row')[0].clientHeight;
			$('#writeModal').css('height', 'calc(100% - ' + h + 'px)');
		}

		$('#writeModal').slideToggle({direction: "up"}).attr('status', 'opened'); //, () => resizeImg() // add this to resize then opened or closed
	} else {
		$('#writeModal').slideToggle({direction: "up"}).attr('status', 'closed'); //, () => resizeImg()
	}
}

function openSearchModal() {
	M.Modal.getInstance($('#searchModal')).open();
}

var activeNote;

function sendNote() {
	let note = $('#note').val().trim();
	if (note.length >= 20) {
		apretaste.send({
			'command': 'PIZARRA ESCRIBIR',
			'data': {'text': note, 'image': notePicture},
			'redirect': false,
			'callback': {'name': 'sendNoteCallback', 'data': note}
		});
	} else {
		showToast('Minimo 20 caracteres');
	}
}

function sendComment() {
	let comment = $('#comment').val().trim();
	if (comment.length >= 2) {
		apretaste.send({
			'command': 'PIZARRA COMENTAR',
			'data': {'comment': comment, 'note': note.id},
			'redirect': false,
			'callback': {'name': 'sendCommentCallback', 'data': comment}
		});
	} else {
		showToast('Escriba algo');
	}
}

function searchText() {
	let search = $('#search').val().trim();
	if (search.length >= 2) {
		apretaste.send({
			'command': 'PIZARRA',
			'data': {'search': search}
		});
	} else {
		showToast('Ingrese algo');
	}
}

function deleteNote() {
	apretaste.send({
		'command': 'PIZARRA ELIMINAR',
		'data': {'note': activeNote},
		'redirect': false,
		callback: {'name': 'deleteCallback', 'data': activeNote}
	});
}

function deleteCallback(id) {
	$('#' + id).remove();
	showToast('Nota eliminada');
}

function themifyNote() {
	let theme = $('#theme').val().trim();
	if (theme.length >= 2) {
		apretaste.send({
			'command': 'PIZARRA TEMIFICAR',
			'data': {'note': activeNote, 'theme': theme},
			'redirect': false,
			'callback': {'name': 'themifyCallback', 'data': theme}
		});
	} else {
		showToast('Ingrese algo');
	}
}

// submit the profile informacion
function submitProfileData() {
	if(!isMyOwnProfile) return;
	// get the array of fields and
	var fields = ['first_name', 'username', 'about_me','gender','year_of_birth', 'highest_school_level','occupation','country','province','usstate','city','religion'];

	// create the JSON of data
	var data = new Object;
	fields.forEach(function(field) {
		var value = $('#'+field).val();
		if(value && value.trim() != '') data[field] = value;
	});

	// save information in the backend
	apretaste.send({
		"command": "PERFIL UPDATE",
		"data": data,
		"redirect": false
	});

	// show confirmation text
	M.toast({html: 'Su informacion se ha salvado correctamente'});
}

function noteLengthValidate() {
	let note = $('#note').val().trim();
	if (note.length <= 300) {
		$('.helper-text').html('Restante: ' + (300 - note.length));
	} else {
		$('.helper-text').html('Limite excedido');
	}
}

function commentLengthValidate() {
	let comment = $('#comment').val().trim();
	if (comment.length <= 250) {
		$('.helper-text').html('Restante: ' + (250 - comment.length));
	} else {
		$('.helper-text').html('Limite excedido');
	}
}

function like(id, type) {
	apretaste.send({
		'command': 'PIZARRA ' + type,
		'data': {'note': id},
		'callback': {
			'name': 'likeCallback',
			'data': {'id': id, 'type': type}
		},
		'redirect': false
	});
}

function likeCallback(data) {
	data = JSON.parse(data)
	id = data.id;
	type = data.type;
	counter = type == 'like' ? 'unlike' : 'like';
	let span = $('#' + id + ' a.' + type + ' span');
	let count = parseInt(span.html());
	span.html(count + 1);
	if ($('#' + id + ' a.' + counter).attr('onclick') == null) {
		span = $('#' + id + ' a.' + counter + ' span');
		count = parseInt(span.html());
		span.html(count - 1);
		$('#' + id + ' a.' + counter).attr('onclick', "like('" + id + "','" + counter + "')");
	}
	$('#' + id + ' a.' + type).removeAttr('onclick');
}

function sendCommentCallback(comment) {
	var color = myUser.gender == "M" ? "pizarra-color-text" : color = myUser.gender == "F" ? "pink-text" : "black-text";

	let serviceImgPath = $('serviceImgPath').attr('data');

	let element = `
	<li class="collection-item avatar row" id="last">
		<div class="col s12">
			<div class="avatar circle" style="`+ getAvatar(myUser.avatar, serviceImgPath, 42)+`"></div>
			<span class="title">
				<a class="` + color + `" onclick="apretaste.send({'command': 'PIZARRA PERFIL', 'data': {'username':'` + myUser.username + `'}});">
					<b>@` + myUser.username + `</b>
				</a>
				<small class="grey-text text-darken-3">` + myUser.location + ` · ` + new Date(Date.now()).toLocaleString() + `</small>
			</span>
			
			<p>` + comment + `</p>
				<div class="col s10 actions">
					<div class="col s4">
						<a class="like" onclick="like('last','like');">
							<i class="material-icons">thumb_up</i>
							<span>0</span>
						</a>
					
					</div>
						<div class="col s4">
							<a class="unlike" onclick="like('last','unlike')">
								<i class="material-icons">thumb_down</i>
								<span>o</span>
							</a>
						</div>
				</div>
			</div>
		</li>`;

	$('#comments').append(element);

	$('#comment').val('');

	$('html, body').animate({
		scrollTop: $("#last").offset().top
	}, 1000);
}

function sendNoteCallback(note) {
	var color = myUser.gender == "M" ? "pizarra-color-text" : color = myUser.gender == "F" ? "pink-text" : "black-text";

	let serviceImgPath = $('serviceImgPath').attr('data');
	let topics = note.match(/(^|\B)#(?![0-9_]+\b)([a-zA-Z0-9_]{1,30})(\b|\r)/g);
	let htmlTopics = "";
	topics = topics.splice(0, 3);

	topics.forEach(function (topic) {
		topic = topic.replace('#', '');
		htmlTopics += `
			<a onclick="apretaste.send({'command': 'PIZARRA','data':{'search':'` + topic + `'}})">
				<b>#` + topic + `</b>
			</a>&nbsp;`;
	});

	let element = `
	<li class="collection-item avatar row" id="last">
		<div class="avatar circle" style="`+getAvatar(myUser.avatar, serviceImgPath, 42)+`"></div>
		<span class="title">
			<a class="` + color + `" onclick="apretaste.send({'command': 'PIZARRA PERFIL', 'data': {'username':'` + myUser.username + `'}});">
				<b>@` + myUser.username + `</b>
			</a>
			<small class="grey-text text-darken-3">` + myUser.location + ` · ` + new Date(Date.now()).toLocaleString() + `</small>
		</span>
		
		<p>` + note + `</p>
		<p>
			` + htmlTopics + `
			</p>
			
			<div class="col s10 actions">
				<div class="col s4">
					<a class="like" onclick="like('last','like');">
						<i class="material-icons">thumb_up</i>
						<span>0</span>
					</a>
				
				</div>
					<div class="col s4">
						<a class="unlike" onclick="like('last','unlike')">
							<i class="material-icons">thumb_down</i>
							<span>0</span>
						</a>
					</div>
				<div class="col s4">
					<a onclick="apretaste.send({'command': 'PIZARRA NOTA','data':{'note':'last'}});">
						<i class="material-icons">comment</i>
						<span>0</span>
					</a>
				</div>
			</div>
		</li>`;

	$('.notes .collection').prepend(element);
	showToast('Nota publicada');

	$('#note').val('');
	toggleWriteModal();

	$('html, body').animate({
		scrollTop: $("#last").offset().top
	}, 1000);
}

function themifyCallback(theme) {
	$('#' + activeNote + ' .topics').append(`
    <a class="grey-text text-darken-2" onclick="apretaste.send({'command': 'PIZARRA','data':{'search':'` + theme + `'}})">
        #` + theme + `
    </a>&nbsp;`);

	if ($('#' + activeNote + ' .topics').children().length == 3) {
		$('#' + activeNote + ' .themifyButton').remove();
	}
}

function togglePopularsMenu() {
	var option1 = $('#populars-nav div:nth-child(1) h5');
	var option2 = $('#populars-nav div:nth-child(2) h5');
	var option1content = $('#popular-users');
	var option2content = $('#popular-topics');

	if(option1.hasClass('pizarra-color-text')){
		option1.removeClass('pizarra-color-text');
		option1.addClass('black-text');

		option2.attr('onclick','');
		option1.attr('onclick','togglePopularsMenu()');

		option2.removeClass('black-text');
		option2.addClass('pizarra-color-text');

		option1content.fadeOut();
		option2content.fadeIn();
	}else{
		option2.removeClass('pizarra-color-text');
		option2.addClass('black-text');

		option1.attr('onclick','');
		option2.attr('onclick','togglePopularsMenu()');

		option1.removeClass('black-text');
		option1.addClass('pizarra-color-text');

		option2content.fadeOut();
		option1content.fadeIn();
	}
}

var notePicture;

function sendFile(base64File) {
	notePicture = base64File;
	var notePictureSrc = "data:image/jpg;base64," + base64File;
	if ($('#notePicture').length == 0) {
		$('#writeModal > .row > .col').append('<img id="notePicture" class="responsive-img"/>');
	}

	$('#notePicture').attr('src', notePictureSrc);
}

function showToast(text) {
	M.toast({html: text});
}

String.prototype.firstUpper = function () {
	return this.charAt(0).toUpperCase() + this.substr(1).toLowerCase();
};

String.prototype.replaceAll = function (search, replacement) {
	return this.split(search).join(replacement);
};

// get list of countries to display
function getCountries() {
	return [
		{code: 'cu', name: 'Cuba'},
		{code: 'us', name: 'Estados Unidos'},
		{code: 'es', name: 'Espana'},
		{code: 'it', name: 'Italia'},
		{code: 'mx', name: 'Mexico'},
		{code: 'br', name: 'Brasil'},
		{code: 'ec', name: 'Ecuador'},
		{code: 'ca', name: 'Canada'},
		{code: 'vz', name: 'Venezuela'},
		{code: 'al', name: 'Alemania'},
		{code: 'co', name: 'Colombia'},
		{code: 'OTRO', name: 'Otro'}
	];
}

///////// CHAT SCRIPTS /////////

var optionsModalActive = false;
var moved = false;
var activeChat;
var activeMessage;
var activeUsername;
var timer;

$(() => {
	if (typeof messages != "undefined") {
		resizeChat();
		$(window).resize(() => resizeChat());
		if(messages.length > 0) $('.chat').scrollTop($('.bubble:last-of-type').offset().top);
		$('#message').focus();
		activeChat = id;
		activeUsername = username;

		setMessagesEventListener();
		$('.footer').addClass('hide');
	}

	$('.modal').modal();
	$('.openchat')
		.on("touchstart", event => { runTimer(); activeChat = event.currentTarget.id; activeName = event.currentTarget.getAttribute('name'); })
		.on("touchmove", event => { clearTimeout(timer); moved = true; })
		.on("touchend", event => { openChat() });

	$('.openchat')
		.on("mousedown", event => { runTimer(); activeChat = event.currentTarget.id; activeName = event.currentTarget.getAttribute('name'); })
		.on("mouseup", event => { openChat() });
});

function openChat() {
	if (!optionsModalActive && !moved){
		var firstName = $('#'+activeChat+' .name').html();
		apretaste.send({ 'command': 'PIZARRA CONVERSACION', 'data': { 'userId': activeChat, 'firstName': firstName }})
	};

	optionsModalActive = false;
	moved = false;
	clearTimeout(timer);
}

function viewProfile() {
	apretaste.send({ 'command': 'PIZARRA PERFIL', 'data': { 'id': activeChat } });
}

function writeModalOpen() {
	optionsModalActive = false;
	M.Modal.getInstance($('#optionsModal')).close();
	M.Modal.getInstance($('#writeMessageModal')).open();
}

function deleteModalOpen() {
	optionsModalActive = false;
	M.Modal.getInstance($('#optionsModal')).close();
	if(typeof messages == "undefined") $('#deleteModal p').html('¿Esta seguro de eliminar su chat con '+ activeName.trim() +'?');
	M.Modal.getInstance($('#deleteModal')).open();
}

function deleteChat(){
	apretaste.send({
		'command': 'CHAT BORRAR',
		'data':{'id':activeChat, 'type': 'chat'},
		'redirect': false,
		'callback':{'name':'deleteChatCallback','data':activeChat}
	})
}

function deleteMessage(){
	apretaste.send({
		'command': 'CHAT BORRAR',
		'data':{'id':activeMessage, 'type': 'message'},
		'redirect': false,
		'callback':{'name':'deleteMessageCallback','data':activeMessage}
	})
}

function deleteChatCallback(chatId){
	$('#'+chatId).remove();
	showToast('Chat eliminado');
}

function deleteMessageCallback(messageId){
	$('#'+messageId).remove();
	showToast('Mensaje eliminado');
}

function runTimer() {
	timer = setTimeout(function () {
		optionsModalActive = true;
		M.Modal.getInstance($('#optionsModal')).open();
	}, 800);
}

function sendMessage() {
	var message = $('#message').val().trim();
	if (message.length > 0) {
		apretaste.send({
			'command': "PIZARRA MENSAJE",
			'data': { 'id': activeChat, 'message': message },
			'redirect': false,
			'callback': { 'name': 'sendMessageCallback', 'data': message }
		});
	}
}

function deleteMatchModalOpen(id, name){
	$('#deleteModal .name').html(name);
	activeId = id;
	M.Modal.getInstance($('#deleteModal')).open();
}

function sendMessageCallback(message) {
	if (typeof messages != "undefined") {
		if (messages.length == 0) {
			// Jquery Bug, fixed in 1.9, insertBefore or After deletes the element and inserts nothing
			// $('#messageField').insertBefore("<div class=\"chat\"></div>");

			$('#nochats').remove();
			$('#chat-row').append("<div class=\"chat\"></div>");
		}

		$('.chat').append(
			"<div class=\"bubble me\" id=\"last\">" +
			message +
			"<br>"+
			"<small>"+(new Date()).toLocaleString('es-ES')+"</small>"+
			"</div>"
		);
	}
	else{
		if(message.length > 70) message = message.substr(0, 70)+'...';
		$('#'+activeChat+' msg').html(message)
	}
	$('#message').val('')
	setMessagesEventListener();
}

function resizeChat(){
	if($('.row').length == 3){
		$('.chat').height($(window).height() - $($('.row')[0]).outerHeight(true) - $('#messageField').outerHeight(true)-20);
	}
	else $('.chat').height($(window).height() - $('#messageField').outerHeight(true)-20);
}

function setMessagesEventListener(){
	$('.bubble')
		.on("touchstart", event => { runTimer(); activeMessage = event.currentTarget.id; })
		.on("touchmove", event => { clearTimeout(timer); moved = true; })
		.on("touchend", event => { clearTimeout(timer); });

	$('.bubble')
		.on("mousedown", event => { runTimer(); activeMessage = event.currentTarget.id; })
		.on("mouseup", event => { clearTimeout(timer); });
}

// Polyfill

if (!Object.keys) {
	Object.keys = (function() {
		'use strict';
		var hasOwnProperty = Object.prototype.hasOwnProperty,
			hasDontEnumBug = !({ toString: null }).propertyIsEnumerable('toString'),
			dontEnums = [
				'toString',
				'toLocaleString',
				'valueOf',
				'hasOwnProperty',
				'isPrototypeOf',
				'propertyIsEnumerable',
				'constructor'
			],
			dontEnumsLength = dontEnums.length;

		return function(obj) {
			if (typeof obj !== 'object' && (typeof obj !== 'function' || obj === null)) {
				throw new TypeError('Object.keys called on non-object');
			}

			var result = [], prop, i;

			for (prop in obj) {
				if (hasOwnProperty.call(obj, prop)) {
					result.push(prop);
				}
			}

			if (hasDontEnumBug) {
				for (i = 0; i < dontEnumsLength; i++) {
					if (hasOwnProperty.call(obj, dontEnums[i])) {
						result.push(dontEnums[i]);
					}
				}
			}
			return result;
		};
	}());
}