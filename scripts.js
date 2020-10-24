var selectedColor;
var occupation = {
	'AMA_DE_CASA': 'Ama de casa',
	'ESTUDIANTE': 'Estudiante',
	'EMPLEADO_PRIVADO': 'Empleado Privado',
	'EMPLEADO_ESTATAL': 'Empleado Estatal',
	'INDEPENDIENTE': 'Trabajador Independiente',
	'JUBILADO': 'Jubilado',
	'DESEMPLEADO': 'Desempleado'
};

$(document).ready(function () {
	$('.fixed-action-btn').floatingActionButton();
	$('.modal').modal();
	$('.tabs').tabs();
	$('.materialboxed').materialbox();

	M.FloatingActionButton.init($('.click-to-toggle'), {
		direction: 'left',
		hoverEnabled: false
	});

	if (typeof activeIcon != "undefined") {
		var menuItem = $($('.footer i')[activeIcon - 1]);
		menuItem.removeClass('grey-text');
		menuItem.removeClass('text-darken-3');
		menuItem.addClass('green-text');
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
		'command': 'PERFIL'
	});
}

function changeColor(color) {
	selectedColor = color;
	$('.mini-card .person-avatar').css('background-color', colors[color]);
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
		hideKeyboard();
		$('#writeModal').slideToggle({
			direction: "up"
		}).attr('status', 'closed');
	}
}

function openSearchModal() {
	M.Modal.getInstance($('#searchModal')).open();
}

function replyUser(user) {
	var comment = $('#comment');
	var currentComment = comment.val();

	if (currentComment.length === 0) comment.val('@' + user);
	else comment.val(currentComment + ' @' + user);
	M.Modal.getInstance($('#newCommentModal')).open();
	comment.focus();
}

function appendTag(tag) {
	var content = $('#note');
	var currentContent = content.val();

	if (currentContent.length === 0) content.val('#' + tag);
	else content.val(currentContent + ' #' + tag);

	content.focus();
}

function searchChat() {
}

function openNote(id) {
	apretaste.send({'command': 'PIZARRA NOTA', 'data': {'note': id}});
}

var activeNote;

function sendNote() {
	hideKeyboard();
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
	hideKeyboard();
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

function openReportModal() {
	M.Modal.getInstance($('#reportModal')).open();
	$('#reportMessage').focus();
}

function reportNote() {
	var message = $('#reportMessage').val().trim();

	if (message.length < 10) {
		showToast('Especifique la razon de su reporte');
		return;
	} else if (message.length > 250) {
		showToast('Mensaje demasiado largo');
		return;
	}

	apretaste.send({
		command: 'PIZARRA REPORTAR',
		data: {
			message: message,
			id: note.id
		},
		callback: {
			name: 'showToast',
			data: 'Reporte enviado'
		},
		redirect: false
	});
}

function searchText() {
	var search = $('#search').val().trim();

	if (search.length >= 2) {
		apretaste.send({
			'command': 'PIZARRA GLOBAL',
			'data': {
				'search': search
			}
		});
	} else {
		showToast('Ingrese algo');
	}
}

function searchTopic(topic) {
	apretaste.send({
		'command': 'PIZARRA GLOBAL',
		'data': {
			'search': '#' + topic
		}
	});
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

function hideKeyboard() {
	if (
		document.activeElement &&
		document.activeElement.blur &&
		typeof document.activeElement.blur === 'function'
	) {
		document.activeElement.blur()
	}
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

	var fields = ['first_name', 'username', 'about_me', 'gender', 'year_of_birth', 'highest_school_level', 'country', 'province', 'city', 'usstate', 'religion', 'occupation']; // create the JSON of data

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

	if (note.length <= 600) {
		$('.helper-text').html('Restante: ' + (600 - note.length));
	} else {
		$('.helper-text').html('Limite excedido');
	}
}

function commentLengthValidate() {
	var comment = $('#comment').val().trim();

	if (comment.length <= 250) {
		$('.helper-text').html(comment.length + '/' + '250');
	} else {
		$('.helper-text').html('Limite excedido');
	}
}

function remainder(size = 250) {
	// get message and remainder amount
	var comment = $('#comment').val().trim();
	var remainder = (comment.length <= size) ? (size - comment.length) : 0;

	// restrict comment size
	if (remainder <= 0) {
		comment = comment.substring(0, size);
		$('#comment').val(comment);
	}

	// update remainder amount
	$('#remainder').html(comment.length);
}

function reportLengthValidate() {
	var message = $('#reportMessage').val().trim();

	if (message.length <= 250) {
		$('#reportModal .helper-text').html(message.length + '/' + '250');
	} else {
		$('#reportModal .helper-text').html('Limite excedido');
	}
}

function like(id, type, pubType = 'note') {
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
	var span = $('#' + id + ' span.' + type + ' span');
	var count = parseInt(span.html());
	span.html(count + 1);

	if ($('#' + id + ' span.' + counter).attr('onclick') == null) {
		span = $('#' + id + ' span.' + counter + ' span');
		count = parseInt(span.html());
		span.html(count - 1);
		$('#' + id + ' span.' + counter).attr('onclick', "like('" + id + "','" + counter + "', '" + pubType + "')");
	}

	$('#' + id + ' span.' + type).removeAttr('onclick');
}

function openProfile(username) {
	apretaste.send({
		'command': 'PERFIL',
		'data': {'username': '@' + username}
	});
}

// Callback functions

function sendCommentCallback(comment) {
	var element =
		"<li class=\"right\" id=\"last\">\n" +
		"    <div class=\"person-avatar circle\" face=\"" + myUser.avatar + "\" color=\"" + myUser.avatarColor + "\"\n" +
		"         size=\"30\" onclick=\"openProfile('" + myUser.username + "')\"></div>\n" +
		"    <div class=\"head\">\n" +
		"        <a onclick=\"openProfile('" + myUser.username + "')\"\n" +
		"           class=\"" + myUser.gender + "\">@" + myUser.username + "</a>\n" +
		"        <span class=\"date\">" + moment().format('MMM D, YYYY h:mm A') + "</span>\n" +
		"    </div>\n" +
		"    <span class=\"text\">" + comment + "</span>\n" +
		"</li>"

	$('#no-comments').remove();

	$('#comments').append(element);
	$('#comment').val('');
	$('html, body').animate({
		scrollTop: $("#last").offset().top - 64
	}, 1000);

	$('#newCommentModal .helper-text').html('0/250');

	$('.person-avatar').each(function (i, item) {
		item.innerHTML = '';
		setElementAsAvatar(item)
	});

	toggleWriteModal();
}

function sendNoteCallback(note) {
	var serviceImgPath = $('serviceImgPath').attr('data');
	var topics = note.match(/(^|\B)#(?![0-9_]+\b)([a-zA-Z0-9_]{1,30})(\b|\r)/g);
	var htmlTopics = "";
	topics = topics != null ? topics.splice(0, 3) : [myUser.topic];
	var hasImage = typeof notePicture != "undefined" ? "<img class=\"responsive-img\" style=\"width: 100%\" src=\"" + serviceImgPath + "/img-prev.png\" onclick=\"apretaste.send({'command': 'PIZARRA NOTA','data':{'note':'last'}});\">" : "";
	topics.forEach(function (topic) {
		topic = topic.replace('#', '');
		htmlTopics +=
			'<div class="chip small" onclick="apretaste.send({\'command\': \'PIZARRA GLOBAL\',\'data\':{\'search\':\'#' + +topic + '\'}})">\n' +
			'    <i class="fa fa-hashtag"></i>' + topic +
			'</div>';
	});
	note = note.escapeHTML();

	var element =
		'<div class="card note" id="last" liked="false"\n' +
		'                 unliked="false">\n' +
		'                <div class="card-person grey lighten-5">\n' +
		'                        <div class="person-avatar circle left"\n' +
		'                             face="' + myUser.avatar + '" color="' + myUser.avatarColor + '"\n' +
		'                             size="30" online="1">\n' +
		'                        </div>\n' +
		'                        <a href="#!" class="' + myUser.gender + '"\n' +
		'                           onclick="apretaste.send({\'command\': \'PERFIL\', \'data\': {\'username\':\'' + myUser.username + '\'}})">\n' +
		'                            @' + myUser.username + '\n' +
		'                        </a>\n' +
		'                    <span class="chip tiny clear right">\n' +
		'                        <i class="material-icons icon">perm_contact_calendar</i>\n' +
		moment().format('MMM D, h:mm A') + '\n' +
		'                    </span>\n' +
		'                </div>\n' +
		'                <div class="card-content">\n' +
		hasImage +
		'                    <p>' + note + '</p>\n' +
		'                    <div class="tags">\n' +
		htmlTopics +
		'                    </div>\n' +
		'                </div>\n' +
		'                <div class="card-action grey lighten-4">\n' +
		'                        <span class="chip like" style="background-color: transparent; padding-left: 0;"\n' +
		'                        onclick="like(\'last\',\'like\');">' +
		'                            <i class="material-icons icon">thumb_up</i>\n' +
		'                            <span>0</span>\n' +
		'                        </span>\n' +
		'                        <span class="chip unlike" style="background-color: transparent;"\n' +
		'                        onclick="like(\'last\',\'unlike\')">' +
		'                            <i class="material-icons icon">thumb_down</i>\n' +
		'                            <span>0</span>\n' +
		'                        </span>\n' +
		'                    <span class="chip" style="background-color: transparent;"\n' +
		'                          onclick="apretaste.send({\'command\': \'PIZARRA NOTA\',\'data\':{\'note\':\'last\'}});">\n' +
		'                        <i class="material-icons icon">comment</i>\n' +
		'                        <span>0</span>\n' +
		'                    </span>\n' +
		'                </div>\n' +
		'            </div>';

	$('.notes > .col').prepend(element);
	showToast('Nota publicada');
	$('#note').val('');
	toggleWriteModal();
	setElementAsAvatar($('#last .person-avatar'))
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

	if (option1.hasClass('green-text')) {
		option1.removeClass('green-text');
		option1.addClass('black-text');
		option2.attr('onclick', '');
		option1.attr('onclick', 'togglePopularsMenu()');
		option2.removeClass('black-text');
		option2.addClass('green-text');
		option1content.fadeOut();
		option2content.fadeIn();
	} else {
		option2.removeClass('green-text');
		option2.addClass('black-text');
		option1.attr('onclick', '');
		option2.attr('onclick', 'togglePopularsMenu()');
		option1.removeClass('black-text');
		option1.addClass('green-text');
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
		'command': 'PERFIL',
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
