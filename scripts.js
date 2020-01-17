"use strict";

var colors = {
	'azul': '#99F9FF',
	'verde': '#9ADB05',
	'rojo': '#FF415B',
	'morado': '#58235E',
	'naranja': '#F38200',
	'amarillo': '#FFE600'
};

var selectedColor;

var avatars = {
	apretin: {caption: "Apretín", gender: 'M'},
	apretina: {caption: "Apretina", gender: 'F'},
	artista: {caption: "Artista", gender: 'M'},
	bandido: {caption: "Bandido", gender: 'M'},
	belleza: {caption: "Belleza", gender: 'F'},
	chica: {caption: "Chica", gender: 'F'},
	coqueta: {caption: "Coqueta", gender: 'F'},
	cresta: {caption: "Cresta", gender: 'M'},
	deportiva: {caption: "Deportiva", gender: 'F'},
	dulce: {caption: "Dulce", gender: 'F'},
	emo: {caption: "Emo", gender: 'M'},
	encapuchado: {caption: "Encapuchado", gender: 'M'},
	extranna: {caption: "Extraña", gender: 'F'},
	fabulosa: {caption: "Fabulosa", gender: 'F'},
	fuerte: {caption: "Fuerte", gender: 'M'},
	ganadero: {caption: "Ganadero", gender: 'M'},
	geek: {caption: "Geek", gender: 'F'},
	genia: {caption: "Genia", gender: 'F'},
	gotica: {caption: "Gótica", gender: 'F'},
	gotico: {caption: "Gótico", gender: 'M'},
	guapo: {caption: "Guapo", gender: 'M'},
	hawaiano: {caption: "Hawaiano", gender: 'M'},
	hippie: {caption: "Hippie", gender: 'M'},
	hombre: {caption: "Hombre", gender: 'M'},
	inconformista: {caption: "Inconformista", gender: 'M'},
	independiente: {caption: "Independiente", gender: 'F'},
	jefe: {caption: "Jefe", gender: 'M'},
	jugadora: {caption: "Jugadora", gender: 'F'},
	mago: {caption: "Mago", gender: 'M'},
	metalero: {caption: "Metalero", gender: 'M'},
	modelo: {caption: "Modelo", gender: 'F'},
	moderna: {caption: "Moderna", gender: 'F'},
	musico: {caption: "Músico", gender: 'M'},
	nerd: {caption: "Nerd", gender: 'M'},
	punk: {caption: "Punk", gender: 'M'},
	punkie: {caption: "Punkie", gender: 'M'},
	rap: {caption: "Rap", gender: 'M'},
	rapear: {caption: "Rapear", gender: 'M'},
	rapero: {caption: "Rapero", gender: 'M'},
	rock: {caption: "Rock", gender: 'M'},
	rockera: {caption: "Rockera", gender: 'F'},
	rubia: {caption: "Rubia", gender: 'F'},
	rudo: {caption: "Rudo", gender: 'M'},
	sencilla: {caption: "Sencilla", gender: 'F'},
	sencillo: {caption: "Sencillo", gender: 'M'},
	sennor: {caption: "Señor", gender: 'M'},
	sennorita: {caption: "Señorita", gender: 'F'},
	sensei: {caption: "Sensei", gender: 'M'},
	surfista: {caption: "Surfista", gender: 'M'},
	tablista: {caption: "Tablista", gender: 'F'},
	vaquera: {caption: "Vaquera", gender: 'F'}
};

$(document).ready(function () {
	$('.fixed-action-btn').floatingActionButton();
	$('.modal').modal();
	$('select').formSelect();
	M.FloatingActionButton.init($('.click-to-toggle'), {
		direction: 'left',
		hoverEnabled: false
	});

	if (typeof activeIcon != "undefined") {
		var menuItem = $($('.footer i')[activeIcon - 1]);
		menuItem.removeClass('grey-text');
		menuItem.removeClass('text-darken-3');
		menuItem.addClass('pizarra-color-text');
	}

	$(window).resize(function () {
		return resizeImg();
	});

	showStateOrProvince();

	if ($('.container:not(#writeModal) > .row').length == 3) {
		var h = $('.container:not(#writeModal) > .row')[2].clientHeight + 8;
		$('.fixed-action-btn').css('bottom', h + 'px');
		$('#writeModal .actions').css('bottom', h - 8 + 'px');
	}

	$('#uploadPhoto').click(function (e) {
		return loadFileToBase64();
	});
	var resizeInterval = setInterval(function () {
		// check until the img has the correct size
		resizeImg();
		if ($('#profile-rounded-img').css('background-size') != 'auto') clearTimeout(resizeInterval);
	}, 1);
	if (typeof notes != "undefined" || typeof chats != "undefined") $('#searchButton').removeClass('hide');
	if (typeof chats != "undefined" || typeof chat != "undefined") $('#chatButton').addClass('hide');

	if (typeof populars != "undefined" || $('#colors-nav').length > 0) {
		if ($('.container > .row').length != 3) $('.container> .row:first-child').css('margin-bottom', '15px');
	}

	$('#chat-row').parent().css('margin-bottom', '0');
});

function resizeImg() {
	if (typeof profile == "undefined") return;
	if ($('.container:not(#writeModal) > .row').length == 3) $('.container:not(#writeModal) > .row:first-child').css('margin-bottom', '0');
	var img = $('#profile-rounded-img');
	var size = $(window).height() / 4; // picture must be 1/4 of the screen

	img.height(size);
	img.width(size);
	img.css('top', -4 - $(window).height() / 8 + 'px'); // align the picture with the div

	$('#edit-fields').css('margin-top', -10 - $(window).height() / 8 + 'px'); // move the row before to the top to fill the empty space

	$('#img-pre').height(img.height() * 0.8); // set the height of the colored div after the photo
}

function getAvatar(avatar, serviceImgPath) {
	return "background-image: url(" + serviceImgPath + "/" + avatar + ".png);";
}

function setAvatar(avatar) {
	if (typeof selectedColor == "undefined") selectedColor = myUser.avatarColor;
	apretaste.send({
		'command': 'PERFIL UPDATE',
		'data': {
			'avatar': avatar,
			'avatarColor': selectedColor
		},
		'redirect': false,
		'callback': {
			'name': 'setAvatarCallback'
		}
	});
}

function setAvatarCallback() {
	apretaste.send({
		'command': 'PIZARRA PERFIL'
	});
}

function changeColor(color) {
	selectedColor = color;
	$('.mini-card div.avatar').css('background-color', colors[color]);
}

function getYears() {
	var year = new Date().getFullYear();
	var years = [];

	for (var i = year - 15; i >= year - 90; i--) {
		years.push(i);
	}

	return years;
}

function toggleWriteModal() {
	var status = $('#writeModal').attr('status');

	if (status == "closed") {
		if ($('.container:not(#writeModal) > .row').length == 3) {
			var h = $('.container:not(#writeModal) > .row')[0].clientHeight;
			$('#writeModal').css('height', 'calc(100% - ' + h + 'px)');
		}

		$('#writeModal').slideToggle({
			direction: "up"
		}).attr('status', 'opened');
		$('#note').focus();
	} else {
		$('#writeModal').slideToggle({
			direction: "up"
		}).attr('status', 'closed');
	}
}

function openSearchModal() {
	M.Modal.getInstance($('#searchModal')).open();
}

function searchChat() {
}

var activeNote;

function sendNote() {
	var note = $('#note').val().trim();

	if (note.length >= 20) {
		apretaste.send({
			'command': 'PIZARRA ESCRIBIR',
			'data': {
				'text': note,
				'image': notePicture
			},
			'redirect': false,
			'callback': {
				'name': 'sendNoteCallback',
				'data': note
			}
		});
	} else {
		showToast('Minimo 20 caracteres');
	}
}

function sendComment() {
	var comment = $('#comment').val().trim();

	if (comment.length >= 2) {
		apretaste.send({
			'command': 'PIZARRA COMENTAR',
			'data': {
				'comment': comment,
				'note': note.id
			},
			'redirect': false,
			'callback': {
				'name': 'sendCommentCallback',
				'data': comment.escapeHTML()
			}
		});
	} else {
		showToast('Escriba algo');
	}
}

function searchText() {
	var search = $('#search').val().trim();

	if (search.length >= 2) {
		apretaste.send({
			'command': 'PIZARRA',
			'data': {
				'search': search
			}
		});
	} else {
		showToast('Ingrese algo');
	}
}

function deleteNote() {
	apretaste.send({
		'command': 'PIZARRA ELIMINAR',
		'data': {
			'note': activeNote
		},
		'redirect': false,
		callback: {
			'name': 'deleteCallback',
			'data': activeNote
		}
	});
}

function deleteCallback(id) {
	$('#' + id).remove();
	showToast('Nota eliminada');
}

function deleteNotification(id) {
	// delete from the backend
	apretaste.send({
		command: 'NOTIFICACIONES LEER',
		data: {
			id: id
		},
		redirect: false
	}); // remove from the view

	$('#' + id).fadeOut(function () {
		$(this).remove(); // show message if all notifications were deleted

		var count = $("ul.collection li").length;

		if (count <= 0) {
			var parent = $('#noti-list').parent();
			$('ul.collection').remove();
			parent.append("\n\t\t\t\t<div class=\"col s12 center\">\n\t\t\t\t<h1 class=\"black-text\">Nada por leer</h1>\n\t\t\t\t<i class=\"material-icons large\">notifications_off</i>\n\t\t\t\t<p>Por ahora usted no tiene ninguna notificaci\xF3n por leer.</p>\n\t\t\t\t</div>\n\t\t\t\t");
		}
	});
}

function themifyNote() {
	var theme = $('#theme').val().trim();

	if (theme.length >= 2) {
		apretaste.send({
			'command': 'PIZARRA TEMIFICAR',
			'data': {
				'note': activeNote,
				'theme': theme
			},
			'redirect': false,
			'callback': {
				'name': 'themifyCallback',
				'data': theme
			}
		});
	} else {
		showToast('Ingrese algo');
	}
} // submit the profile informacion


function submitProfileData() {
	if (myUser.id != profile.id) return; // get the array of fields and

	var fields = ['first_name', 'username', 'about_me', 'gender', 'year_of_birth', 'highest_school_level', 'country', 'province', 'usstate', 'religion']; // create the JSON of data

	var data = new Object();
	fields.forEach(function (field) {
		var value = $('#' + field).val();
		if (value && value.trim() != '' && !(field == "username" && value.trim() == '@' + profile.username)) data[field] = value;
	}); // save information in the backend

	apretaste.send({
		"command": "PERFIL UPDATE",
		"data": data,
		"redirect": false
	}); // show confirmation text

	M.toast({
		html: 'Su informacion se ha salvado correctamente'
	});
}

function noteLengthValidate() {
	var note = $('#note').val().trim();

	if (note.length <= 300) {
		$('.helper-text').html('Restante: ' + (300 - note.length));
	} else {
		$('.helper-text').html('Limite excedido');
	}
}

function commentLengthValidate() {
	var comment = $('#comment').val().trim();

	if (comment.length <= 250) {
		$('.helper-text').html('Restante: ' + (250 - comment.length));
	} else {
		$('.helper-text').html('Limite excedido');
	}
}

function like(id, type) {
	var pubType = arguments.length > 2 && arguments[2] !== undefined ? arguments[2] : 'note';
	var element = pubType == 'note' ? $('#' + id) : $('#comments #' + id);
	if (type == "like" && element.attr('liked') == 'true' || type == "unlike" && element.attr('unliked') == 'true') return;
	var data = pubType == 'note' ? {
		'note': id
	} : {
		'comment': id
	};
	apretaste.send({
		'command': 'PIZARRA ' + type,
		'data': data,
		'callback': {
			'name': 'likeCallback',
			'data': JSON.stringify({
				'id': id,
				'type': type,
				'pubType': pubType
			})
		},
		'redirect': false
	});
}

function likeCallback(data) {
	var data = JSON.parse(data);
	var id = data.id;
	var type = data.type;
	var pubType = data.pubType;
	var note = pubType == 'note' ? $('#' + id) : $('#comments #' + id);

	if (type == "like") {
		note.attr('liked', 'true');
		note.attr('unliked', 'false');
	} else {
		note.attr('unliked', 'true');
		note.attr('liked', 'false');
	}

	var counter = type == 'like' ? 'unlike' : 'like';
	var span = $('#' + id + ' a.' + type + ' span');
	var count = parseInt(span.html());
	span.html(count + 1);

	if ($('#' + id + ' a.' + counter).attr('onclick') == null) {
		span = $('#' + id + ' a.' + counter + ' span');
		count = parseInt(span.html());
		span.html(count - 1);
		$('#' + id + ' a.' + counter).attr('onclick', "like('" + id + "','" + counter + "', '" + pubType + "')");
	}

	$('#' + id + ' a.' + type).removeAttr('onclick');
}

function sendCommentCallback(comment) {
	var color = myUser.gender == "M" ? "pizarra-color-text" : color = myUser.gender == "F" ? "pink-text" : "black-text";
	var serviceImgPath = $('serviceImgPath').attr('data');
	var element = "\n\t<li class=\"collection-item avatar row\" id=\"last\">\n\t\t\t<div class=\"avatar circle\" style=\"" + getAvatar(myUser.avatar, serviceImgPath, 42) + " background-color: " + colors[myUser.avatarColor] + ";\"></div>\n\t\t\t<i class=\"material-icons online-icon\">brightness_1</i>\n\t\t\t<span class=\"title\">\n\t\t\t\t<a class=\"" + color + "\" onclick=\"apretaste.send({'command': 'PIZARRA PERFIL', 'data': {'username':'" + myUser.username + "'}});\">\n\t\t\t\t\t<b>@" + myUser.username + "</b>\n\t\t\t\t</a>\n\t\t\t\t<small class=\"grey-text text-darken-3\">" + myUser.location + " \xB7 " + Date.prototype.nowFormated() + "</small>\n\t\t\t</span>\n\t\t\t\n\t\t\t<p>" + comment + "</p>\n\t\t\t\t<div class=\"col s10 actions\">\n\t\t\t\t\t<div class=\"col s4\">\n\t\t\t\t\t\t<a class=\"like\" onclick=\"like('last','like', 'comment');\">\n\t\t\t\t\t\t\t<i class=\"material-icons\">thumb_up</i>\n\t\t\t\t\t\t\t<span>0</span>\n\t\t\t\t\t\t</a>\n\t\t\t\t\t\n\t\t\t\t\t</div>\n\t\t\t\t\t\t<div class=\"col s4\">\n\t\t\t\t\t\t\t<a class=\"unlike\" onclick=\"like('last','unlike', 'comment')\">\n\t\t\t\t\t\t\t\t<i class=\"material-icons\">thumb_down</i>\n\t\t\t\t\t\t\t\t<span>0</span>\n\t\t\t\t\t\t\t</a>\n\t\t\t\t\t\t</div>\n\t\t\t\t</div>\n\t\t</li>";
	$('#comments').append(element);
	$('#comment').val('');
	$('html, body').animate({
		scrollTop: $("#last").offset().top
	}, 1000);
}

function sendNoteCallback(note) {
	var color = myUser.gender == "M" ? "pizarra-color-text" : color = myUser.gender == "F" ? "pink-text" : "black-text";
	var serviceImgPath = $('serviceImgPath').attr('data');
	var topics = note.match(/(^|\B)#(?![0-9_]+\b)([a-zA-Z0-9_]{1,30})(\b|\r)/g);
	var htmlTopics = "";
	topics = topics != null ? topics.splice(0, 3) : [myUser.topic];
	var hasImage = typeof notePicture != "undefined" ? "<img class=\"responsive-img\" src=\"" + serviceImgPath + "/img-prev.png\" onclick=\"apretaste.send({'command': 'PIZARRA NOTA','data':{'note':'last'}});\">" : "";
	topics.forEach(function (topic) {
		topic = topic.replace('#', '');
		htmlTopics += "\n\t\t\t<a onclick=\"apretaste.send({'command': 'PIZARRA','data':{'search':'#" + topic + "'}})\">\n\t\t\t\t<b>#" + topic + "</b>\n\t\t\t</a>&nbsp;";
	});
	note = note.escapeHTML();
	var element = "\n\t<li class=\"collection-item avatar row\" id=\"last\">\n\t\t<div class=\"avatar circle\" style=\"" + getAvatar(myUser.avatar, serviceImgPath, 42) + " background-color: " + colors[myUser.avatarColor] + ";\"></div>\n\t\t<i class=\"material-icons online-icon\">brightness_1</i>\n\t\t<span class=\"title\">\n\t\t\t<a class=\"" + color + "\" onclick=\"apretaste.send({'command': 'PIZARRA PERFIL', 'data': {'username':'" + myUser.username + "'}});\">\n\t\t\t\t<b>@" + myUser.username + "</b>\n\t\t\t</a>\n\t\t\t<small class=\"grey-text text-darken-3\">" + myUser.location + " \xB7 " + Date.prototype.nowFormated() + "</small>\n\t\t</span>\n\t\t\n\t\t<p>" + note + "</p>\n\t\t" + hasImage + "\n\t\t<p>\n\t\t\t" + htmlTopics + "\n\t\t\t</p>\n\t\t\t\n\t\t\t<div class=\"col s10 actions\">\n\t\t\t\t<div class=\"col s4\">\n\t\t\t\t\t<a class=\"like\" onclick=\"like('last','like');\">\n\t\t\t\t\t\t<i class=\"material-icons\">thumb_up</i>\n\t\t\t\t\t\t<span>0</span>\n\t\t\t\t\t</a>\n\t\t\t\t\n\t\t\t\t</div>\n\t\t\t\t\t<div class=\"col s4\">\n\t\t\t\t\t\t<a class=\"unlike\" onclick=\"like('last','unlike')\">\n\t\t\t\t\t\t\t<i class=\"material-icons\">thumb_down</i>\n\t\t\t\t\t\t\t<span>0</span>\n\t\t\t\t\t\t</a>\n\t\t\t\t\t</div>\n\t\t\t\t<div class=\"col s4\">\n\t\t\t\t\t<a onclick=\"apretaste.send({'command': 'PIZARRA NOTA','data':{'note':'last'}});\">\n\t\t\t\t\t\t<i class=\"material-icons\">comment</i>\n\t\t\t\t\t\t<span>0</span>\n\t\t\t\t\t</a>\n\t\t\t\t</div>\n\t\t\t</div>\n\t\t</li>";
	$('.notes .collection').prepend(element);
	showToast('Nota publicada');
	$('#note').val('');
	toggleWriteModal();
	$('html, body').animate({
		scrollTop: $("#last").offset().top
	}, 1000);
}

function themifyCallback(theme) {
	$('#' + activeNote + ' .topics').append("\n    <a class=\"grey-text text-darken-2\" onclick=\"apretaste.send({'command': 'PIZARRA','data':{'search':'" + theme + "'}})\">\n        #" + theme + "\n    </a>&nbsp;");

	if ($('#' + activeNote + ' .topics').children().length == 3) {
		$('#' + activeNote + ' .themifyButton').remove();
	}
}

function togglePopularsMenu() {
	var option1 = $('#populars-nav div:nth-child(1) h5');
	var option2 = $('#populars-nav div:nth-child(2) h5');
	var option1content = $('#popular-users');
	var option2content = $('#popular-topics');

	if (option1.hasClass('pizarra-color-text')) {
		option1.removeClass('pizarra-color-text');
		option1.addClass('black-text');
		option2.attr('onclick', '');
		option1.attr('onclick', 'togglePopularsMenu()');
		option2.removeClass('black-text');
		option2.addClass('pizarra-color-text');
		option1content.fadeOut();
		option2content.fadeIn();
	} else {
		option2.removeClass('pizarra-color-text');
		option2.addClass('black-text');
		option1.attr('onclick', '');
		option2.attr('onclick', 'togglePopularsMenu()');
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
	M.toast({
		html: text
	});
}

String.prototype.firstUpper = function () {
	return this.charAt(0).toUpperCase() + this.substr(1).toLowerCase();
};

String.prototype.replaceAll = function (search, replacement) {
	return this.split(search).join(replacement);
}; // get list of countries to display


function getCountries() {
	return [{
		code: 'cu',
		name: 'Cuba'
	}, {
		code: 'us',
		name: 'Estados Unidos'
	}, {
		code: 'es',
		name: 'Espana'
	}, {
		code: 'it',
		name: 'Italia'
	}, {
		code: 'mx',
		name: 'Mexico'
	}, {
		code: 'br',
		name: 'Brasil'
	}, {
		code: 'ec',
		name: 'Ecuador'
	}, {
		code: 'ca',
		name: 'Canada'
	}, {
		code: 'vz',
		name: 'Venezuela'
	}, {
		code: 'al',
		name: 'Alemania'
	}, {
		code: 'co',
		name: 'Colombia'
	}, {
		code: 'OTRO',
		name: 'Otro'
	}];
}

var province = {
	'PINAR_DEL_RIO': 'Pinar del Río',
	'ARTEMISA': 'Artemisa',
	'LA_HABANA': 'La Habana',
	'MAYABEQUE': 'Mayabeque',
	'MATANZAS': 'Matanzas',
	'CIENFUEGOS': 'Cienfuegos',
	'VILLA_CLARA': 'Villa Clara',
	'SANCTI_SPIRITUS': 'Sancti Spíritus',
	'CIEGO_DE_AVILA': 'Ciego de Ávila',
	'CAMAGUEY': 'Camagüey',
	'LAS_TUNAS': 'Las Tunas',
	'GRANMA': 'Granma',
	'HOLGUIN': 'Holguín',
	'SANTIAGO_DE_CUBA': 'Santiago de Cuba',
	'GUANTANAMO': 'Guantánamo',
	'ISLA_DE_LA_JUVENTUD': 'Isla de la Juventud'
};

function showStateOrProvince() {
	var country = $('#country').val();
	var province = $('.province-div');
	var usstate = $('.usstate-div');

	switch (country) {
		case 'cu':
			province.show();
			usstate.hide();
			break;

		case 'us':
			usstate.show();
			province.hide();
			break;

		default:
			usstate.hide();
			province.hide();
			break;
	}
} ///////// CHAT SCRIPTS /////////


var optionsModalActive = false;
var moved = false;
var activeChat;
var activeMessage;
var activeUsername;
var timer;
$(function () {
	if (typeof messages != "undefined") {
		resizeChat();
		$(window).resize(function () {
			return resizeChat();
		});
		if (messages.length > 0) $('.chat').scrollTop($('.bubble:last-of-type').offset().top);
		$('#message').focus();
		activeChat = id;
		activeUsername = username;
		setMessagesEventListener();
		$('.footer').addClass('hide');
	}

	$('.modal').modal();
	$('.openchat').on("touchstart", function (event) {
		runTimer();
		activeChat = event.currentTarget.id;
		var activeName = event.currentTarget.getAttribute('name');
	}).on("touchmove", function (event) {
		clearTimeout(timer);
		moved = true;
	}).on("touchend", function (event) {
		openChat();
	});
	$('.openchat').on("mousedown", function (event) {
		runTimer();
		activeChat = event.currentTarget.id;
		var activeName = event.currentTarget.getAttribute('name');
	}).on("mouseup", function (event) {
		openChat();
	});
});

function openChat() {
	if (!optionsModalActive && !moved) {
		var firstName = $('#' + activeChat + ' .name').html();
		apretaste.send({
			'command': 'PIZARRA CONVERSACION',
			'data': {
				'userId': activeChat,
				'firstName': firstName
			}
		});
	}

	optionsModalActive = false;
	moved = false;
	clearTimeout(timer);
}

function viewProfile() {
	apretaste.send({
		'command': 'PIZARRA PERFIL',
		'data': {
			'id': activeChat
		}
	});
}

function writeModalOpen() {
	optionsModalActive = false;
	M.Modal.getInstance($('#optionsModal')).close();
	M.Modal.getInstance($('#writeMessageModal')).open();
}

function deleteModalOpen() {
	optionsModalActive = false;
	M.Modal.getInstance($('#optionsModal')).close();
	if (typeof messages == "undefined") $('#deleteModal p').html('¿Esta seguro de eliminar su chat con ' + activeName.trim() + '?');
	M.Modal.getInstance($('#deleteModal')).open();
}

function deleteChat() {
	apretaste.send({
		'command': 'CHAT BORRAR',
		'data': {
			'id': activeChat,
			'type': 'chat'
		},
		'redirect': false,
		'callback': {
			'name': 'deleteChatCallback',
			'data': activeChat
		}
	});
}

function deleteMessage() {
	apretaste.send({
		'command': 'CHAT BORRAR',
		'data': {
			'id': activeMessage,
			'type': 'message'
		},
		'redirect': false,
		'callback': {
			'name': 'deleteMessageCallback',
			'data': activeMessage
		}
	});
}

function deleteChatCallback(chatId) {
	$('#' + chatId).remove();
	showToast('Chat eliminado');
}

function deleteMessageCallback(messageId) {
	$('#' + messageId).remove();
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
			'data': {
				'id': activeChat,
				'message': message
			},
			'redirect': false,
			'callback': {
				'name': 'sendMessageCallback',
				'data': message
			}
		});
	}
}

function deleteMatchModalOpen(id, name) {
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

		$('.chat').append("<div class=\"bubble me\" id=\"last\">" + message + "<br>" + "<small>" + new Date().toLocaleString('es-ES') + "</small>" + "</div>");
	} else {
		if (message.length > 70) message = message.substr(0, 70) + '...';
		$('#' + activeChat + ' msg').html(message);
	}

	$('#message').val('');
	setMessagesEventListener();
}

function resizeChat() {
	if ($('.row').length == 3) {
		$('.chat').height($(window).height() - $($('.row')[0]).outerHeight(true) - $('#messageField').outerHeight(true) - 20);
	} else $('.chat').height($(window).height() - $('#messageField').outerHeight(true) - 20);
}

function setMessagesEventListener() {
	$('.bubble').on("touchstart", function (event) {
		runTimer();
		activeMessage = event.currentTarget.id;
	}).on("touchmove", function (event) {
		clearTimeout(timer);
		moved = true;
	}).on("touchend", function (event) {
		clearTimeout(timer);
	});
	$('.bubble').on("mousedown", function (event) {
		runTimer();
		activeMessage = event.currentTarget.id;
	}).on("mouseup", function (event) {
		clearTimeout(timer);
	});
} // Polyfill

function _typeof(obj) {
	if (typeof Symbol === "function" && typeof Symbol.iterator === "symbol") {
		_typeof = function _typeof(obj) {
			return typeof obj;
		};
	} else {
		_typeof = function _typeof(obj) {
			return obj && typeof Symbol === "function" && obj.constructor === Symbol && obj !== Symbol.prototype ? "symbol" : typeof obj;
		};
	}
	return _typeof(obj);
}

if (!Object.keys) {
	Object.keys = function () {
		'use strict';

		var hasOwnProperty = Object.prototype.hasOwnProperty,
			hasDontEnumBug = !{
				toString: null
			}.propertyIsEnumerable('toString'),
			dontEnums = ['toString', 'toLocaleString', 'valueOf', 'hasOwnProperty', 'isPrototypeOf', 'propertyIsEnumerable', 'constructor'],
			dontEnumsLength = dontEnums.length;
		return function (obj) {
			if (_typeof(obj) !== 'object' && (typeof obj !== 'function' || obj === null)) {
				throw new TypeError('Object.keys called on non-object');
			}

			var result = [],
				prop,
				i;

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
	}();
}

if (!String.prototype.includes) {
	(function () {
		'use strict'; // needed to support `apply`/`call` with `undefined`/`null`

		var toString = {}.toString;

		var defineProperty = function () {
			// IE 8 only supports `Object.defineProperty` on DOM elements
			try {
				var object = {};
				var $defineProperty = Object.defineProperty;
				var result = $defineProperty(object, object, object) && $defineProperty;
			} catch (error) {
			}

			return result;
		}();

		var indexOf = ''.indexOf;

		var includes = function includes(search) {
			if (this == null) {
				throw TypeError();
			}

			var string = String(this);

			if (search && toString.call(search) == '[object RegExp]') {
				throw TypeError();
			}

			var stringLength = string.length;
			var searchString = String(search);
			var searchLength = searchString.length;
			var position = arguments.length > 1 ? arguments[1] : undefined; // `ToInteger`

			var pos = position ? Number(position) : 0;

			if (pos != pos) {
				// better `isNaN`
				pos = 0;
			}

			var start = Math.min(Math.max(pos, 0), stringLength); // Avoid the `indexOf` call if no match is possible

			if (searchLength + start > stringLength) {
				return false;
			}

			return indexOf.call(string, searchString, pos) != -1;
		};

		if (defineProperty) {
			defineProperty(String.prototype, 'includes', {
				'value': includes,
				'configurable': true,
				'writable': true
			});
		} else {
			String.prototype.includes = includes;
		}
	})();
}

String.prototype.escapeHTML = function () {
	var htmlEscapes = {
		'&': '&amp;',
		'<': '&lt;',
		'>': '&gt;',
		'"': '&quot;',
		"'": '&#x27;',
		'/': '&#x2F;'
	};
	var htmlEscaper = /[&<>"'\/]/g;
	return ('' + this).replace(htmlEscaper, function (match) {
		return htmlEscapes[match];
	});
};

Date.prototype.nowFormated = function () {
	var now = new Date(); // This current millisecond on user's computer.

	var format = "{D}/{M}/{Y} · {h}:{m}{ap}";
	var Month = now.getMonth() + 1;
	format = format.replace(/\{M\}/g, Month);
	var Mday = now.getDate();
	format = format.replace(/\{D\}/g, Mday);
	var Year = now.getFullYear().toString().slice(2);
	format = format.replace(/\{Y\}/g, Year);
	var h = now.getHours();
	var pm = h > 11;

	if (h > 12) {
		h -= 12;
	}

	;
	var ap = pm ? "pm" : "am";
	format = format.replace(/\{ap\}/g, ap);
	var hh = h;
	format = format.replace(/\{h\}/g, hh);
	var mm = now.getMinutes();

	if (mm < 10) {
		mm = "0" + mm;
	}

	format = format.replace(/\{m\}/g, mm);
	return format;
};

