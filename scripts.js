// jquery rich text component
(function ($) {
	$.fn.richText = function (options) {
		var settings = $.extend({
			bold: true,
			italic: true,
			underline: true,
			leftAlign: true,
			centerAlign: true,
			rightAlign: true,
			justify: true,
			ol: true,
			ul: true,
			heading: true,
			fonts: true,
			fontList: ["Arial", "Arial Black", "Comic Sans MS", "Courier New", "Geneva", "Georgia", "Helvetica", "Impact", "Lucida Console", "Tahoma", "Times New Roman", "Verdana"],
			fontColor: true,
			fontSize: true,
			imageUpload: true,
			fileUpload: true,
			videoEmbed: true,
			urls: true,
			table: true,
			removeStyles: true,
			code: true,
			colors: [],
			fileHTML: "",
			imageHTML: "",
			translations: {
				title: "Title",
				white: "White",
				black: "Black",
				brown: "Brown",
				beige: "Beige",
				darkBlue: "Dark Blue",
				blue: "Blue",
				lightBlue: "Light Blue",
				darkRed: "Dark Red",
				red: "Red",
				darkGreen: "Dark Green",
				green: "Green",
				purple: "Purple",
				darkTurquois: "Dark Turquois",
				turquois: "Turquois",
				darkOrange: "Dark Orange",
				orange: "Orange",
				yellow: "Yellow",
				imageURL: "Image URL",
				fileURL: "File URL",
				linkText: "Link text",
				url: "URL",
				size: "Size",
				responsive: "Responsive",
				text: "Text",
				openIn: "Open in",
				sameTab: "Same tab",
				newTab: "New tab",
				align: "Align",
				left: "Left",
				justify: "Justify",
				center: "Center",
				right: "Right",
				rows: "Rows",
				columns: "Columns",
				add: "Add",
				pleaseEnterURL: "Please enter an URL",
				videoURLnotSupported: "Video URL not supported",
				pleaseSelectImage: "Please select an image",
				pleaseSelectFile: "Please select a file",
				bold: "Bold",
				italic: "Italic",
				underline: "Underline",
				alignLeft: "Align left",
				alignCenter: "Align centered",
				alignRight: "Align right",
				addOrderedList: "Add ordered list",
				addUnorderedList: "Add unordered list",
				addHeading: "Add Heading/title",
				addFont: "Add font",
				addFontColor: "Add font color",
				addFontSize: "Add font size",
				addImage: "Add image",
				addVideo: "Add video",
				addFile: "Add file",
				addURL: "Add URL",
				addTable: "Add table",
				removeStyles: "Remove styles",
				code: "Show HTML code",
				undo: "Undo",
				redo: "Redo",
				close: "Close"
			},
			youtubeCookies: false,
			useSingleQuotes: false,
			height: 0,
			heightPercentage: 0,
			id: "",
			class: "",
			useParagraph: false,
			maxlength: 0,
			callback: undefined
		}, options);
		var $inputElement = $(this);
		$inputElement.addClass("richText-initial");
		var $editor, $toolbarList = $("<ul />"), $toolbarElement = $("<li />"), $btnBold = $("<a />", {
			class: "richText-btn",
			"data-command": "bold",
			title: settings.translations.bold,
			html: '<span class="fa fa-bold"></span>'
		}), $btnItalic = $("<a />", {
			class: "richText-btn",
			"data-command": "italic",
			title: settings.translations.italic,
			html: '<span class="fa fa-italic"></span>'
		}), $btnUnderline = $("<a />", {
			class: "richText-btn",
			"data-command": "underline",
			title: settings.translations.underline,
			html: '<span class="fa fa-underline"></span>'
		}), $btnJustify = $("<a />", {
			class: "richText-btn",
			"data-command": "justifyFull",
			title: settings.translations.justify,
			html: '<span class="fa fa-align-justify"></span>'
		}), $btnLeftAlign = $("<a />", {
			class: "richText-btn",
			"data-command": "justifyLeft",
			title: settings.translations.alignLeft,
			html: '<span class="fa fa-align-left"></span>'
		}), $btnCenterAlign = $("<a />", {
			class: "richText-btn",
			"data-command": "justifyCenter",
			title: settings.translations.alignCenter,
			html: '<span class="fa fa-align-center"></span>'
		}), $btnRightAlign = $("<a />", {
			class: "richText-btn",
			"data-command": "justifyRight",
			title: settings.translations.alignRight,
			html: '<span class="fa fa-align-right"></span>'
		}), $btnOL = $("<a />", {
			class: "richText-btn",
			"data-command": "insertOrderedList",
			title: settings.translations.addOrderedList,
			html: '<span class="fa fa-list-ol"></span>'
		}), $btnUL = $("<a />", {
			class: "richText-btn",
			"data-command": "insertUnorderedList",
			title: settings.translations.addUnorderedList,
			html: '<span class="fa fa-list"></span>'
		}), $btnHeading = $("<a />", {
			class: "richText-btn",
			title: settings.translations.addHeading,
			html: '<span class="fa fa-header fa-heading"></span>'
		}), $btnFont = $("<a />", {
			class: "richText-btn",
			title: settings.translations.addFont,
			html: '<span class="fa fa-font"></span>'
		}), $btnFontColor = $("<a />", {
			class: "richText-btn",
			title: settings.translations.addFontColor,
			html: '<span class="fa fa-paint-brush"></span>'
		}), $btnFontSize = $("<a />", {
			class: "richText-btn",
			title: settings.translations.addFontSize,
			html: '<span class="fa fa-text-height"></span>'
		}), $btnImageUpload = $("<a />", {
			class: "richText-btn",
			title: settings.translations.addImage,
			html: '<span class="fa fa-image"></span>'
		}), $btnVideoEmbed = $("<a />", {
			class: "richText-btn",
			title: settings.translations.addVideo,
			html: '<span class="fa fa-video-camera fa-video"></span>'
		}), $btnFileUpload = $("<a />", {
			class: "richText-btn",
			title: settings.translations.addFile,
			html: '<span class="fa fa-file-text-o far fa-file-alt"></span>'
		}), $btnURLs = $("<a />", {
			class: "richText-btn",
			title: settings.translations.addURL,
			html: '<span class="fa fa-link"></span>'
		}), $btnTable = $("<a />", {
			class: "richText-btn",
			title: settings.translations.addTable,
			html: '<span class="fa fa-table"></span>'
		}), $btnRemoveStyles = $("<a />", {
			class: "richText-btn",
			"data-command": "removeFormat",
			title: settings.translations.removeStyles,
			html: '<span class="fa fa-recycle"></span>'
		}), $btnCode = $("<a />", {
			class: "richText-btn",
			"data-command": "toggleCode",
			title: settings.translations.code,
			html: '<span class="fa fa-code"></span>'
		});
		var $dropdownOuter = $("<div />", {class: "richText-dropdown-outer"});
		var $dropdownClose = $("<span />", {
			class: "richText-dropdown-close",
			html: '<span title="' + settings.translations.close + '"><span class="fa fa-times"></span></span>'
		});
		var $dropdownList = $("<ul />", {class: "richText-dropdown"}),
			$dropdownBox = $("<div />", {class: "richText-dropdown"}), $form = $("<div />", {class: "richText-form"}),
			$formItem = $("<div />", {class: "richText-form-item"}), $formLabel = $("<label />"),
			$formInput = $("<input />", {type: "text"}), $formInputFile = $("<input />", {type: "file"}),
			$formInputSelect = $("<select />"),
			$formButton = $("<button />", {text: settings.translations.add, class: "btn"});
		var savedSelection;
		var editorID = "richText-" + Math.random().toString(36).substring(7);
		var ignoreSave = false, $resizeImage = null;
		var history = [];
		history[editorID] = [];
		var historyPosition = [];
		historyPosition[editorID] = 0;
		var $titles = $dropdownList.clone();
		$titles.append($("<li />", {html: '<a data-command="formatBlock" data-option="h1">' + settings.translations.title + " #1</a>"}));
		$titles.append($("<li />", {html: '<a data-command="formatBlock" data-option="h2">' + settings.translations.title + " #2</a>"}));
		$titles.append($("<li />", {html: '<a data-command="formatBlock" data-option="h3">' + settings.translations.title + " #3</a>"}));
		$titles.append($("<li />", {html: '<a data-command="formatBlock" data-option="h4">' + settings.translations.title + " #4</a>"}));
		$btnHeading.append($dropdownOuter.clone().append($titles.prepend($dropdownClose.clone())));
		var fonts = settings.fontList;
		var $fonts = $dropdownList.clone();
		for (var i = 0; i < fonts.length; i++) {
			$fonts.append($("<li />", {html: '<a style="font-family:' + fonts[i] + ';" data-command="fontName" data-option="' + fonts[i] + '">' + fonts[i] + "</a>"}))
		}
		$btnFont.append($dropdownOuter.clone().append($fonts.prepend($dropdownClose.clone())));
		var fontSizes = [24, 18, 16, 14, 12];
		var $fontSizes = $dropdownList.clone();
		for (var i = 0; i < fontSizes.length; i++) {
			$fontSizes.append($("<li />", {html: '<a style="font-size:' + fontSizes[i] + 'px;" data-command="fontSize" data-option="' + fontSizes[i] + '">' + settings.translations.text + " " + fontSizes[i] + "px</a>"}))
		}
		$btnFontSize.append($dropdownOuter.clone().append($fontSizes.prepend($dropdownClose.clone())));
		var $fontColors = $dropdownList.clone();
		$fontColors.html(loadColors("forecolor"));
		$btnFontColor.append($dropdownOuter.clone().append($fontColors.prepend($dropdownClose.clone())));
		var $linksDropdown = $dropdownBox.clone();
		var $linksForm = $form.clone().attr("id", "richText-URL").attr("data-editor", editorID);
		$linksForm.append($formItem.clone().append($formLabel.clone().text(settings.translations.url).attr("for", "url")).append($formInput.clone().attr("id", "url")));
		$linksForm.append($formItem.clone().append($formLabel.clone().text(settings.translations.text).attr("for", "urlText")).append($formInput.clone().attr("id", "urlText")));
		$linksForm.append($formItem.clone().append($formLabel.clone().text(settings.translations.openIn).attr("for", "openIn")).append($formInputSelect.clone().attr("id", "openIn").append($("<option />", {
			value: "_self",
			text: settings.translations.sameTab
		})).append($("<option />", {value: "_blank", text: settings.translations.newTab}))));
		$linksForm.append($formItem.clone().append($formButton.clone()));
		$linksDropdown.append($linksForm);
		$btnURLs.append($dropdownOuter.clone().append($linksDropdown.prepend($dropdownClose.clone())));
		var $videoDropdown = $dropdownBox.clone();
		var $videoForm = $form.clone().attr("id", "richText-Video").attr("data-editor", editorID);
		$videoForm.append($formItem.clone().append($formLabel.clone().text(settings.translations.url).attr("for", "videoURL")).append($formInput.clone().attr("id", "videoURL")));
		$videoForm.append($formItem.clone().append($formLabel.clone().text(settings.translations.size).attr("for", "size")).append($formInputSelect.clone().attr("id", "size").append($("<option />", {
			value: "responsive",
			text: settings.translations.responsive
		})).append($("<option />", {value: "640x360", text: "640x360"})).append($("<option />", {
			value: "560x315",
			text: "560x315"
		})).append($("<option />", {value: "480x270", text: "480x270"})).append($("<option />", {
			value: "320x180",
			text: "320x180"
		}))));
		$videoForm.append($formItem.clone().append($formButton.clone()));
		$videoDropdown.append($videoForm);
		$btnVideoEmbed.append($dropdownOuter.clone().append($videoDropdown.prepend($dropdownClose.clone())));
		var $imageDropdown = $dropdownBox.clone();
		var $imageForm = $form.clone().attr("id", "richText-Image").attr("data-editor", editorID);
		if (settings.imageHTML && ($(settings.imageHTML).find("#imageURL").length > 0 || $(settings.imageHTML).attr("id") === "imageURL")) {
			$imageForm.html(settings.imageHTML)
		} else {
			$imageForm.append($formItem.clone().append($formLabel.clone().text(settings.translations.imageURL).attr("for", "imageURL")).append($formInput.clone().attr("id", "imageURL")));
			$imageForm.append($formItem.clone().append($formLabel.clone().text(settings.translations.align).attr("for", "align")).append($formInputSelect.clone().attr("id", "align").append($("<option />", {
				value: "left",
				text: settings.translations.left
			})).append($("<option />", {
				value: "center",
				text: settings.translations.center
			})).append($("<option />", {value: "right", text: settings.translations.right}))))
		}
		$imageForm.append($formItem.clone().append($formButton.clone()));
		$imageDropdown.append($imageForm);
		$btnImageUpload.append($dropdownOuter.clone().append($imageDropdown.prepend($dropdownClose.clone())));
		var $fileDropdown = $dropdownBox.clone();
		var $fileForm = $form.clone().attr("id", "richText-File").attr("data-editor", editorID);
		if (settings.fileHTML && ($(settings.fileHTML).find("#fileURL").length > 0 || $(settings.fileHTML).attr("id") === "fileURL")) {
			$fileForm.html(settings.fileHTML)
		} else {
			$fileForm.append($formItem.clone().append($formLabel.clone().text(settings.translations.fileURL).attr("for", "fileURL")).append($formInput.clone().attr("id", "fileURL")));
			$fileForm.append($formItem.clone().append($formLabel.clone().text(settings.translations.linkText).attr("for", "fileText")).append($formInput.clone().attr("id", "fileText")))
		}
		$fileForm.append($formItem.clone().append($formButton.clone()));
		$fileDropdown.append($fileForm);
		$btnFileUpload.append($dropdownOuter.clone().append($fileDropdown.prepend($dropdownClose.clone())));
		var $tableDropdown = $dropdownBox.clone();
		var $tableForm = $form.clone().attr("id", "richText-Table").attr("data-editor", editorID);
		$tableForm.append($formItem.clone().append($formLabel.clone().text(settings.translations.rows).attr("for", "tableRows")).append($formInput.clone().attr("id", "tableRows").attr("type", "number")));
		$tableForm.append($formItem.clone().append($formLabel.clone().text(settings.translations.columns).attr("for", "tableColumns")).append($formInput.clone().attr("id", "tableColumns").attr("type", "number")));
		$tableForm.append($formItem.clone().append($formButton.clone()));
		$tableDropdown.append($tableForm);
		$btnTable.append($dropdownOuter.clone().append($tableDropdown.prepend($dropdownClose.clone())));

		function init() {
			var value, attributes, attributes_html = "";
			if (settings.useParagraph !== false) {
				document.execCommand("DefaultParagraphSeparator", false, "p")
			}
			if ($inputElement.prop("tagName") === "TEXTAREA") {
			} else if ($inputElement.val()) {
				value = $inputElement.val();
				attributes = $inputElement.prop("attributes");
				$.each(attributes, function () {
					if (this.name) {
						attributes_html += " " + this.name + '="' + this.value + '"'
					}
				});
				$inputElement.replaceWith($("<textarea" + attributes_html + ' data-richtext="init">' + value + "</textarea>"));
				$inputElement = $('[data-richtext="init"]');
				$inputElement.removeAttr("data-richtext")
			} else if ($inputElement.html()) {
				value = $inputElement.html();
				attributes = $inputElement.prop("attributes");
				$.each(attributes, function () {
					if (this.name) {
						attributes_html += " " + this.name + '="' + this.value + '"'
					}
				});
				$inputElement.replaceWith($("<textarea" + attributes_html + ' data-richtext="init">' + value + "</textarea>"));
				$inputElement = $('[data-richtext="init"]');
				$inputElement.removeAttr("data-richtext")
			} else {
				attributes = $inputElement.prop("attributes");
				$.each(attributes, function () {
					if (this.name) {
						attributes_html += " " + this.name + '="' + this.value + '"'
					}
				});
				$inputElement.replaceWith($("<textarea" + attributes_html + ' data-richtext="init"></textarea>'));
				$inputElement = $('[data-richtext="init"]');
				$inputElement.removeAttr("data-richtext")
			}
			$editor = $("<div />", {class: "richText"});
			var $toolbar = $("<div />", {class: "richText-toolbar"});
			var $editorView = $("<div />", {class: "richText-editor", id: editorID, contenteditable: true});
			$toolbar.append($toolbarList);
			if (settings.bold === true) {
				$toolbarList.append($toolbarElement.clone().append($btnBold))
			}
			if (settings.italic === true) {
				$toolbarList.append($toolbarElement.clone().append($btnItalic))
			}
			if (settings.underline === true) {
				$toolbarList.append($toolbarElement.clone().append($btnUnderline))
			}
			if (settings.leftAlign === true) {
				$toolbarList.append($toolbarElement.clone().append($btnLeftAlign))
			}
			if (settings.centerAlign === true) {
				$toolbarList.append($toolbarElement.clone().append($btnCenterAlign))
			}
			if (settings.rightAlign === true) {
				$toolbarList.append($toolbarElement.clone().append($btnRightAlign))
			}
			if (settings.justify === true) {
				$toolbarList.append($toolbarElement.clone().append($btnJustify))
			}
			if (settings.ol === true) {
				$toolbarList.append($toolbarElement.clone().append($btnOL))
			}
			if (settings.ul === true) {
				$toolbarList.append($toolbarElement.clone().append($btnUL))
			}
			if (settings.fonts === true && settings.fontList.length > 0) {
				$toolbarList.append($toolbarElement.clone().append($btnFont))
			}
			if (settings.fontSize === true) {
				$toolbarList.append($toolbarElement.clone().append($btnFontSize))
			}
			if (settings.heading === true) {
				$toolbarList.append($toolbarElement.clone().append($btnHeading))
			}
			if (settings.fontColor === true) {
				$toolbarList.append($toolbarElement.clone().append($btnFontColor))
			}
			if (settings.imageUpload === true) {
				$toolbarList.append($toolbarElement.clone().append($btnImageUpload))
			}
			if (settings.fileUpload === true) {
				$toolbarList.append($toolbarElement.clone().append($btnFileUpload))
			}
			if (settings.videoEmbed === true) {
				$toolbarList.append($toolbarElement.clone().append($btnVideoEmbed))
			}
			if (settings.urls === true) {
				$toolbarList.append($toolbarElement.clone().append($btnURLs))
			}
			if (settings.table === true) {
				$toolbarList.append($toolbarElement.clone().append($btnTable))
			}
			if (settings.removeStyles === true) {
				$toolbarList.append($toolbarElement.clone().append($btnRemoveStyles))
			}
			if (settings.code === true) {
				$toolbarList.append($toolbarElement.clone().append($btnCode))
			}
			$editorView.html($inputElement.val());
			$editor.append($toolbar);
			$editor.append($editorView);
			$editor.append($inputElement.clone().hide());
			$inputElement.replaceWith($editor);
			$editor.append($("<div />", {class: "richText-toolbar"}).append($("<a />", {
				class: "richText-undo is-disabled",
				html: '<span class="fa fa-undo"></span>',
				title: settings.translations.undo
			})).append($("<a />", {
				class: "richText-redo is-disabled",
				html: '<span class="fa fa-repeat fa-redo"></span>',
				title: settings.translations.redo
			})).append($("<a />", {class: "richText-help", html: '<span class="fa fa-question-circle"></span>'})));
			if (settings.maxlength > 0) {
				$editor.data("maxlength", settings.maxlength);
				$editor.children(".richText-toolbar").children(".richText-help").before($("<a />", {
					class: "richText-length",
					text: "0/" + settings.maxlength
				}))
			}
			if (settings.height && settings.height > 0) {
				$editor.children(".richText-editor, .richText-initial").css({
					"min-height": settings.height + "px",
					height: settings.height + "px"
				})
			} else if (settings.heightPercentage && settings.heightPercentage > 0) {
				var parentHeight = $editor.parent().innerHeight();
				var height = settings.heightPercentage / 100 * parentHeight;
				height -= $toolbar.outerHeight() * 2;
				height -= parseInt($editor.css("margin-top"));
				height -= parseInt($editor.css("margin-bottom"));
				height -= parseInt($editor.find(".richText-editor").css("padding-top"));
				height -= parseInt($editor.find(".richText-editor").css("padding-bottom"));
				$editor.children(".richText-editor, .richText-initial").css({
					"min-height": height + "px",
					height: height + "px"
				})
			}
			if (settings.class) {
				$editor.addClass(settings.class)
			}
			if (settings.id) {
				$editor.attr("id", settings.id)
			}
			fixFirstLine();
			history[editorID].push($editor.find("textarea").val());
			if (settings.callback && typeof settings.callback === "function") {
				settings.callback($editor)
			}
		}

		init();
		$editor.find(".richText-help").on("click", function () {
			var $editor = $(this).parents(".richText");
			if ($editor) {
				var $outer = $("<div />", {
					class: "richText-help-popup",
					style: "position:absolute;top:0;right:0;bottom:0;left:0;background-color: rgba(0,0,0,0.3);"
				});
				var $inner = $("<div />", {style: "position:relative;margin:60px auto;padding:20px;background-color:#FAFAFA;width:70%;font-family:Calibri,Verdana,Helvetica,sans-serif;font-size:small;"});
				var $content = $("<div />", {html: '<span id="closeHelp" style="display:block;position:absolute;top:0;right:0;padding:10px;cursor:pointer;" title="' + settings.translations.close + '"><span class="fa fa-times"></span></span>'});
				$content.append('<h3 style="margin:0;">RichText</h3>');
				$content.append('<hr><br>Powered by <a href="https://github.com/webfashionist/RichText" target="_blank">webfashionist/RichText</a> (Github) <br>License: <a href="https://github.com/webfashionist/RichText/blob/master/LICENSE" target="_blank">AGPL-3.0</a>');
				$outer.append($inner.append($content));
				$editor.append($outer);
				$outer.on("click", "#closeHelp", function () {
					$(this).parents(".richText-help-popup").remove()
				})
			}
		});
		$(document).on("click", ".richText-undo, .richText-redo", function (e) {
			var $this = $(this);
			var $editor = $this.parents(".richText");
			if ($this.hasClass("richText-undo") && !$this.hasClass("is-disabled")) {
				undo($editor)
			} else if ($this.hasClass("richText-redo") && !$this.hasClass("is-disabled")) {
				redo($editor)
			}
		});
		$(document).on("input change blur keydown keyup", ".richText-editor", function (e) {
			if ((e.keyCode === 9 || e.keyCode === "9") && e.type === "keydown") {
				e.preventDefault();
				tabifyEditableTable(window, e);
				return false
			}
			fixFirstLine();
			updateTextarea();
			doSave($(this).attr("id"));
			updateMaxLength($(this).attr("id"))
		});
		$(document).on("contextmenu", ".richText-editor", function (e) {
			var $list = $("<ul />", {class: "list-rightclick richText-list"});
			var $li = $("<li />");
			$(".richText-editor").find(".richText-editNode").removeClass("richText-editNode");
			var $target = $(e.target);
			var $richText = $target.parents(".richText");
			var $toolbar = $richText.find(".richText-toolbar");
			var positionX = e.pageX - $richText.offset().left;
			var positionY = e.pageY - $richText.offset().top;
			$list.css({top: positionY, left: positionX});
			if ($target.prop("tagName") === "A") {
				e.preventDefault();
				$list.append($li.clone().html('<span class="fa fa-link"></span>'));
				$target.parents(".richText").append($list);
				$list.find(".fa-link").on("click", function () {
					$(".list-rightclick.richText-list").remove();
					$target.addClass("richText-editNode");
					var $popup = $toolbar.find("#richText-URL");
					$popup.find("input#url").val($target.attr("href"));
					$popup.find("input#urlText").val($target.text());
					$popup.find("select#openIn").val($target.attr("target"));
					$toolbar.find(".richText-btn").children(".fa-link").parents("li").addClass("is-selected")
				});
				return false
			} else if ($target.prop("tagName") === "IMG") {
				e.preventDefault();
				$list.append($li.clone().html('<span class="fa fa-image"></span>'));
				$target.parents(".richText").append($list);
				$list.find(".fa-image").on("click", function () {
					var align;
					if ($target.parent("div").length > 0 && $target.parent("div").attr("style") === "text-align:center;") {
						align = "center"
					} else {
						align = $target.attr("align")
					}
					$(".list-rightclick.richText-list").remove();
					$target.addClass("richText-editNode");
					var $popup = $toolbar.find("#richText-Image");
					$popup.find("input#imageURL").val($target.attr("src"));
					$popup.find("select#align").val(align);
					$toolbar.find(".richText-btn").children(".fa-image").parents("li").addClass("is-selected")
				});
				return false
			}
		});
		$(document).on("input change blur", ".richText-initial", function () {
			if (settings.useSingleQuotes === true) {
				$(this).val(changeAttributeQuotes($(this).val()))
			}
			var editorID = $(this).siblings(".richText-editor").attr("id");
			updateEditor(editorID);
			doSave(editorID);
			updateMaxLength(editorID)
		});
		$(document).on("dblclick mouseup", ".richText-editor", function () {
			var editorID = $(this).attr("id");
			doSave(editorID)
		});
		$(document).on("click", "#richText-Video button.btn", function (event) {
			event.preventDefault();
			var $button = $(this);
			var $form = $button.parent(".richText-form-item").parent(".richText-form");
			if ($form.attr("data-editor") === editorID) {
				var url = $form.find("input#videoURL").val();
				var size = $form.find("select#size").val();
				if (!url) {
					$form.prepend($("<div />", {
						style: "color:red;display:none;",
						class: "form-item is-error",
						text: settings.translations.pleaseEnterURL
					}));
					$form.children(".form-item.is-error").slideDown();
					setTimeout(function () {
						$form.children(".form-item.is-error").slideUp(function () {
							$(this).remove()
						})
					}, 5e3)
				} else {
					var html = "";
					html = getVideoCode(url, size);
					if (!html) {
						$form.prepend($("<div />", {
							style: "color:red;display:none;",
							class: "form-item is-error",
							text: settings.translations.videoURLnotSupported
						}));
						$form.children(".form-item.is-error").slideDown();
						setTimeout(function () {
							$form.children(".form-item.is-error").slideUp(function () {
								$(this).remove()
							})
						}, 5e3)
					} else {
						if (settings.useSingleQuotes === true) {
						} else {
						}
						restoreSelection(editorID, true);
						pasteHTMLAtCaret(html);
						updateTextarea();
						$form.find("input#videoURL").val("");
						$(".richText-toolbar li.is-selected").removeClass("is-selected")
					}
				}
			}
		});
		$(document).on("mousedown", function (e) {
			var $target = $(e.target);
			if (!$target.hasClass("richText-list") && $target.parents(".richText-list").length === 0) {
				$(".richText-list.list-rightclick").remove();
				if (!$target.hasClass("richText-form") && $target.parents(".richText-form").length === 0) {
					$(".richText-editNode").each(function () {
						var $this = $(this);
						$this.removeClass("richText-editNode");
						if ($this.attr("class") === "") {
							$this.removeAttr("class")
						}
					})
				}
			}
			if ($target.prop("tagName") === "IMG" && $target.parents("#" + editorID)) {
				startX = e.pageX;
				startY = e.pageY;
				startW = $target.innerWidth();
				startH = $target.innerHeight();
				var left = $target.offset().left;
				var right = $target.offset().left + $target.innerWidth();
				var bottom = $target.offset().top + $target.innerHeight();
				var top = $target.offset().top;
				var resize = false;
				$target.css({cursor: "default"});
				if (startY <= bottom && startY >= bottom - 20 && startX >= right - 20 && startX <= right) {
					$resizeImage = $target;
					$resizeImage.css({cursor: "nwse-resize"});
					resize = true
				}
				if ((resize === true || $resizeImage) && !$resizeImage.data("width")) {
					$resizeImage.data("width", $target.parents("#" + editorID).innerWidth());
					$resizeImage.data("height", $target.parents("#" + editorID).innerHeight() * 3);
					e.preventDefault()
				} else if (resize === true || $resizeImage) {
					e.preventDefault()
				} else {
					$resizeImage = null
				}
			}
		});
		$(document).mouseup(function () {
			if ($resizeImage) {
				$resizeImage.css({cursor: "default"})
			}
			$resizeImage = null
		}).mousemove(function (e) {
			if ($resizeImage !== null) {
				var maxWidth = $resizeImage.data("width");
				var currentWidth = $resizeImage.width();
				var maxHeight = $resizeImage.data("height");
				var currentHeight = $resizeImage.height();
				if (startW + e.pageX - startX <= maxWidth && startH + e.pageY - startY <= maxHeight) {
					$resizeImage.innerWidth(startW + e.pageX - startX);
					updateTextarea()
				} else if (startW + e.pageX - startX <= currentWidth && startH + e.pageY - startY <= currentHeight) {
					$resizeImage.innerWidth(startW + e.pageX - startX);
					updateTextarea()
				}
			}
		});
		$(document).on("click", "#richText-URL button.btn", function (event) {
			event.preventDefault();
			var $button = $(this);
			var $form = $button.parent(".richText-form-item").parent(".richText-form");
			if ($form.attr("data-editor") === editorID) {
				var url = $form.find("input#url").val();
				var text = $form.find("input#urlText").val();
				var target = $form.find("#openIn").val();
				if (!target) {
					target = "_self"
				}
				if (!text) {
					text = url
				}
				if (!url) {
					$form.prepend($("<div />", {
						style: "color:red;display:none;",
						class: "form-item is-error",
						text: settings.translations.pleaseEnterURL
					}));
					$form.children(".form-item.is-error").slideDown();
					setTimeout(function () {
						$form.children(".form-item.is-error").slideUp(function () {
							$(this).remove()
						})
					}, 5e3)
				} else {
					var html = "";
					if (settings.useSingleQuotes === true) {
						html = "<a href='" + url + "' target='" + target + "'>" + text + "</a>"
					} else {
						html = '<a href="' + url + '" target="' + target + '">' + text + "</a>"
					}
					restoreSelection(editorID, false, true);
					var $editNode = $(".richText-editNode");
					if ($editNode.length > 0 && $editNode.prop("tagName") === "A") {
						$editNode.attr("href", url);
						$editNode.attr("target", target);
						$editNode.text(text);
						$editNode.removeClass("richText-editNode");
						if ($editNode.attr("class") === "") {
							$editNode.removeAttr("class")
						}
					} else {
						pasteHTMLAtCaret(html)
					}
					$form.find("input#url").val("");
					$form.find("input#urlText").val("");
					$(".richText-toolbar li.is-selected").removeClass("is-selected")
				}
			}
		});
		$(document).on("click", "#richText-Image button.btn", function (event) {
			event.preventDefault();
			var $button = $(this);
			var $form = $button.parent(".richText-form-item").parent(".richText-form");
			if ($form.attr("data-editor") === editorID) {
				var url = $form.find("#imageURL").val();
				var align = $form.find("select#align").val();
				if (!align) {
					align = "center"
				}
				if (!url) {
					$form.prepend($("<div />", {
						style: "color:red;display:none;",
						class: "form-item is-error",
						text: settings.translations.pleaseSelectImage
					}));
					$form.children(".form-item.is-error").slideDown();
					setTimeout(function () {
						$form.children(".form-item.is-error").slideUp(function () {
							$(this).remove()
						})
					}, 5e3)
				} else {
					var html = "";
					if (settings.useSingleQuotes === true) {
						if (align === "center") {
							html = "<div style='text-align:center;'><img src='" + url + "'></div>"
						} else {
							html = "<img src='" + url + "' align='" + align + "'>"
						}
					} else {
						if (align === "center") {
							html = '<div style="text-align:center;"><img src="' + url + '"></div>'
						} else {
							html = '<img src="' + url + '" align="' + align + '">'
						}
					}
					restoreSelection(editorID, true);
					var $editNode = $(".richText-editNode");
					if ($editNode.length > 0 && $editNode.prop("tagName") === "IMG") {
						$editNode.attr("src", url);
						if ($editNode.parent("div").length > 0 && $editNode.parent("div").attr("style") === "text-align:center;" && align !== "center") {
							$editNode.unwrap("div");
							$editNode.attr("align", align)
						} else if (($editNode.parent("div").length === 0 || $editNode.parent("div").attr("style") !== "text-align:center;") && align === "center") {
							$editNode.wrap('<div style="text-align:center;"></div>');
							$editNode.removeAttr("align")
						} else {
							$editNode.attr("align", align)
						}
						$editNode.removeClass("richText-editNode");
						if ($editNode.attr("class") === "") {
							$editNode.removeAttr("class")
						}
					} else {
						pasteHTMLAtCaret(html)
					}
					$form.find("input#imageURL").val("");
					$(".richText-toolbar li.is-selected").removeClass("is-selected")
				}
			}
		});
		$(document).on("click", "#richText-File button.btn", function (event) {
			event.preventDefault();
			var $button = $(this);
			var $form = $button.parent(".richText-form-item").parent(".richText-form");
			if ($form.attr("data-editor") === editorID) {
				var url = $form.find("#fileURL").val();
				var text = $form.find("#fileText").val();
				if (!text) {
					text = url
				}
				if (!url) {
					$form.prepend($("<div />", {
						style: "color:red;display:none;",
						class: "form-item is-error",
						text: settings.translations.pleaseSelectFile
					}));
					$form.children(".form-item.is-error").slideDown();
					setTimeout(function () {
						$form.children(".form-item.is-error").slideUp(function () {
							$(this).remove()
						})
					}, 5e3)
				} else {
					var html = "";
					if (settings.useSingleQuotes === true) {
						html = "<a href='" + url + "' target='_blank'>" + text + "</a>"
					} else {
						html = '<a href="' + url + '" target="_blank">' + text + "</a>"
					}
					restoreSelection(editorID, true);
					pasteHTMLAtCaret(html);
					$form.find("input#fileURL").val("");
					$form.find("input#fileText").val("");
					$(".richText-toolbar li.is-selected").removeClass("is-selected")
				}
			}
		});
		$(document).on("click", "#richText-Table button.btn", function (event) {
			event.preventDefault();
			var $button = $(this);
			var $form = $button.parent(".richText-form-item").parent(".richText-form");
			if ($form.attr("data-editor") === editorID) {
				var rows = $form.find("input#tableRows").val();
				var columns = $form.find("input#tableColumns").val();
				if (!rows || rows <= 0) {
					rows = 2
				}
				if (!columns || columns <= 0) {
					columns = 2
				}
				var html = "";
				if (settings.useSingleQuotes === true) {
					html = "<table class='table-1'><tbody>"
				} else {
					html = '<table class="table-1"><tbody>'
				}
				for (var i = 1; i <= rows; i++) {
					html += "<tr>";
					for (var n = 1; n <= columns; n++) {
						html += "<td> </td>"
					}
					html += "</tr>"
				}
				html += "</tbody></table>";
				restoreSelection(editorID, true);
				pasteHTMLAtCaret(html);
				$form.find("input#tableColumns").val("");
				$form.find("input#tableRows").val("");
				$(".richText-toolbar li.is-selected").removeClass("is-selected")
			}
		});
		$(document).on("click", function (event) {
			var $clickedElement = $(event.target);
			if ($clickedElement.parents(".richText-toolbar").length === 0) {
			} else if ($clickedElement.hasClass("richText-dropdown-outer")) {
				$clickedElement.parent("a").parent("li").removeClass("is-selected")
			} else if ($clickedElement.find(".richText").length > 0) {
				$(".richText-toolbar li").removeClass("is-selected")
			} else if ($clickedElement.parent().hasClass("richText-dropdown-close")) {
				$(".richText-toolbar li").removeClass("is-selected")
			} else if ($clickedElement.hasClass("richText-btn") && $(event.target).children(".richText-dropdown-outer").length > 0) {
				$clickedElement.parent("li").addClass("is-selected");
				if ($clickedElement.children(".fa,svg").hasClass("fa-link")) {
					restoreSelection(editorID, false, true);
					var selectedText = getSelectedText();
					$clickedElement.find("input#urlText").val("");
					$clickedElement.find("input#url").val("");
					if (selectedText) {
						$clickedElement.find("input#urlText").val(selectedText)
					}
				} else if ($clickedElement.hasClass("fa-image")) {
				}
			}
		});
		$(document).on("click", ".richText-toolbar a[data-command]", function (event) {
			var $button = $(this);
			var $toolbar = $button.closest(".richText-toolbar");
			var $editor = $toolbar.siblings(".richText-editor");
			var id = $editor.attr("id");
			if ($editor.length > 0 && id === editorID && (!$button.parent("li").attr("data-disable") || $button.parent("li").attr("data-disable") === "false")) {
				event.preventDefault();
				var command = $(this).data("command");
				if (command === "toggleCode") {
					toggleCode($editor.attr("id"))
				} else {
					var option = null;
					if ($(this).data("option")) {
						option = $(this).data("option").toString();
						if (option.match(/^h[1-6]$/)) {
							command = "heading"
						}
					}
					formatText(command, option, id);
					if (command === "removeFormat") {
						$editor.find("*").each(function () {
							var keepAttributes = ["id", "class", "name", "action", "method", "src", "align", "alt", "title", "style", "webkitallowfullscreen", "mozallowfullscreen", "allowfullscreen", "width", "height", "frameborder"];
							var element = $(this);
							var attributes = $.map(this.attributes, function (item) {
								return item.name
							});
							$.each(attributes, function (i, item) {
								if (keepAttributes.indexOf(item) < 0 && item.substr(0, 5) !== "data-") {
									element.removeAttr(item)
								}
							});
							if (element.prop("tagName") === "A") {
								element.replaceWith(function () {
									return $("<span />", {html: $(this).html()})
								})
							}
						});
						formatText("formatBlock", "div", id)
					}
					$editor.find("div:empty,p:empty,li:empty,h1:empty,h2:empty,h3:empty,h4:empty,h5:empty,h6:empty").remove();
					$editor.find("h1,h2,h3,h4,h5,h6").unwrap("h1,h2,h3,h4,h5,h6")
				}
			}
			$button.parents("li.is-selected").removeClass("is-selected")
		});

		function formatText(command, option, editorID) {
			if (typeof option === "undefined") {
				option = null
			}
			doRestore(editorID);
			if (command === "heading" && getSelectedText()) {
				pasteHTMLAtCaret("<" + option + ">" + getSelectedText() + "</" + option + ">")
			} else if (command === "fontSize" && parseInt(option) > 0) {
				var selection = getSelectedText();
				selection = (selection + "").replace(/([^>\r\n]?)(\r\n|\n\r|\r|\n)/g, "$1" + "<br>" + "$2");
				var html = settings.useSingleQuotes ? "<span style='font-size:" + option + "px;'>" + selection + "</span>" : '<span style="font-size:' + option + 'px;">' + selection + "</span>";
				pasteHTMLAtCaret(html)
			} else {
				document.execCommand(command, false, option)
			}
		}

		function updateTextarea() {
			var $editor = $("#" + editorID);
			var content = $editor.html();
			if (settings.useSingleQuotes === true) {
				content = changeAttributeQuotes(content)
			}
			$editor.siblings(".richText-initial").val(content)
		}

		function updateEditor(editorID) {
			var $editor = $("#" + editorID);
			var content = $editor.siblings(".richText-initial").val();
			$editor.html(content)
		}

		function saveSelection(editorID) {
			var containerEl = document.getElementById(editorID);
			var range, start, end, type;
			if (window.getSelection && document.createRange) {
				var sel = window.getSelection && window.getSelection();
				if (sel && sel.rangeCount > 0 && $(sel.anchorNode).parents("#" + editorID).length > 0) {
					range = window.getSelection().getRangeAt(0);
					var preSelectionRange = range.cloneRange();
					preSelectionRange.selectNodeContents(containerEl);
					preSelectionRange.setEnd(range.startContainer, range.startOffset);
					start = preSelectionRange.toString().length;
					end = start + range.toString().length;
					type = start === end ? "caret" : "selection";
					anchor = sel.anchorNode;
					start = type === "caret" && anchor !== false ? start : preSelectionRange.toString().length;
					end = type === "caret" && anchor !== false ? end : start + range.toString().length;
					return {start: start, end: end, type: type, anchor: anchor, editorID: editorID}
				}
			}
			return savedSelection ? savedSelection : {start: 0, end: 0}
		}

		function restoreSelection(editorID, media, url) {
			var containerEl = document.getElementById(editorID);
			var savedSel = savedSelection;
			if (!savedSel) {
				savedSel = {
					start: 0,
					end: 0,
					type: "caret",
					editorID: editorID,
					anchor: $("#" + editorID).children("div")[0]
				}
			}
			if (savedSel.editorID !== editorID) {
				return false
			} else if (media === true) {
				containerEl = savedSel.anchor ? savedSel.anchor : containerEl
			} else if (url === true) {
				if (savedSel.start === 0 && savedSel.end === 0) {
					containerEl = savedSel.anchor ? savedSel.anchor : containerEl
				}
			}
			if (window.getSelection && document.createRange) {
				var charIndex = 0, range = document.createRange();
				if (!range || !containerEl) {
					window.getSelection().removeAllRanges();
					return true
				}
				range.setStart(containerEl, 0);
				range.collapse(true);
				var nodeStack = [containerEl], node, foundStart = false, stop = false;
				while (!stop && (node = nodeStack.pop())) {
					if (node.nodeType === 3) {
						var nextCharIndex = charIndex + node.length;
						if (!foundStart && savedSel.start >= charIndex && savedSel.start <= nextCharIndex) {
							range.setStart(node, savedSel.start - charIndex);
							foundStart = true
						}
						if (foundStart && savedSel.end >= charIndex && savedSel.end <= nextCharIndex) {
							range.setEnd(node, savedSel.end - charIndex);
							stop = true
						}
						charIndex = nextCharIndex
					} else {
						var i = node.childNodes.length;
						while (i--) {
							nodeStack.push(node.childNodes[i])
						}
					}
				}
				var sel = window.getSelection();
				sel.removeAllRanges();
				sel.addRange(range)
			}
		}

		function tabifyEditableTable(win, e) {
			if (e.keyCode !== 9) {
				return false
			}
			var sel;
			if (win.getSelection) {
				sel = win.getSelection();
				if (sel.rangeCount > 0) {
					var textNode = null, direction = null;
					if (!e.shiftKey) {
						direction = "next";
						textNode = sel.focusNode.nodeName === "TD" ? sel.focusNode.nextSibling != null ? sel.focusNode.nextSibling : sel.focusNode.parentNode.nextSibling != null ? sel.focusNode.parentNode.nextSibling.childNodes[0] : null : sel.focusNode.parentNode.nextSibling != null ? sel.focusNode.parentNode.nextSibling : sel.focusNode.parentNode.parentNode.nextSibling != null ? sel.focusNode.parentNode.parentNode.nextSibling.childNodes[0] : null
					} else {
						direction = "previous";
						textNode = sel.focusNode.nodeName === "TD" ? sel.focusNode.previousSibling != null ? sel.focusNode.previousSibling : sel.focusNode.parentNode.previousSibling != null ? sel.focusNode.parentNode.previousSibling.childNodes[sel.focusNode.parentNode.previousSibling.childNodes.length - 1] : null : sel.focusNode.parentNode.previousSibling != null ? sel.focusNode.parentNode.previousSibling : sel.focusNode.parentNode.parentNode.previousSibling != null ? sel.focusNode.parentNode.parentNode.previousSibling.childNodes[sel.focusNode.parentNode.parentNode.previousSibling.childNodes.length - 1] : null
					}
					if (textNode != null) {
						sel.collapse(textNode, Math.min(textNode.length, sel.focusOffset + 1));
						if (textNode.textContent != null) {
							sel.selectAllChildren(textNode)
						}
						e.preventDefault();
						return true
					} else if (textNode === null && direction === "next" && sel.focusNode.nodeName === "TD") {
						var $table = $(sel.focusNode).parents("table");
						var cellsPerLine = $table.find("tr").first().children("td").length;
						var $tr = $("<tr />");
						var $td = $("<td />");
						for (var i = 1; i <= cellsPerLine; i++) {
							$tr.append($td.clone())
						}
						$table.append($tr);
						tabifyEditableTable(window, {
							keyCode: 9, shiftKey: false, preventDefault: function () {
							}
						})
					}
				}
			}
			return false
		}

		function getSelectedText() {
			var range;
			if (window.getSelection) {
				range = window.getSelection();
				return range.toString() ? range.toString() : range.focusNode.nodeValue
			} else if (document.selection.createRange) {
				range = document.selection.createRange();
				return range.text
			}
			return false
		}

		function doSave(editorID) {
			var $textarea = $(".richText-editor#" + editorID).siblings(".richText-initial");
			addHistory($textarea.val(), editorID);
			savedSelection = saveSelection(editorID)
		}

		function updateMaxLength(editorID) {
			var $editorInner = $(".richText-editor#" + editorID);
			var $editor = $editorInner.parents(".richText");
			if (!$editor.data("maxlength")) {
				return true
			}
			var color;
			var maxLength = parseInt($editor.data("maxlength"));
			var content = $editorInner.text();
			var percentage = content.length / maxLength * 100;
			if (percentage > 99) {
				color = "red"
			} else if (percentage >= 90) {
				color = "orange"
			} else {
				color = "black"
			}
			$editor.find(".richText-length").html('<span class="' + color + '">' + content.length + "</span>/" + maxLength);
			if (content.length > maxLength) {
				undo($editor);
				return false
			}
			return true
		}

		function addHistory(val, id) {
			if (!history[id]) {
				return false
			}
			if (history[id].length - 1 > historyPosition[id]) {
				history[id].length = historyPosition[id] + 1
			}
			if (history[id][history[id].length - 1] !== val) {
				history[id].push(val)
			}
			historyPosition[id] = history[id].length - 1;
			setHistoryButtons(id)
		}

		function setHistoryButtons(id) {
			if (historyPosition[id] <= 0) {
				$editor.find(".richText-undo").addClass("is-disabled")
			} else {
				$editor.find(".richText-undo").removeClass("is-disabled")
			}
			if (historyPosition[id] >= history[id].length - 1 || history[id].length === 0) {
				$editor.find(".richText-redo").addClass("is-disabled")
			} else {
				$editor.find(".richText-redo").removeClass("is-disabled")
			}
		}

		function undo($editor) {
			var id = $editor.children(".richText-editor").attr("id");
			historyPosition[id]--;
			if (!historyPosition[id] && historyPosition[id] !== 0) {
				return false
			}
			var value = history[id][historyPosition[id]];
			$editor.find("textarea").val(value);
			$editor.find(".richText-editor").html(value);
			setHistoryButtons(id)
		}

		function redo($editor) {
			var id = $editor.children(".richText-editor").attr("id");
			historyPosition[id]++;
			if (!historyPosition[id] && historyPosition[id] !== 0) {
				return false
			}
			var value = history[id][historyPosition[id]];
			$editor.find("textarea").val(value);
			$editor.find(".richText-editor").html(value);
			setHistoryButtons(id)
		}

		function doRestore(id) {
			if (savedSelection) {
				restoreSelection(id ? id : savedSelection.editorID)
			}
		}

		function pasteHTMLAtCaret(html) {
			var sel, range;
			if (window.getSelection) {
				sel = window.getSelection();
				if (sel.getRangeAt && sel.rangeCount) {
					range = sel.getRangeAt(0);
					range.deleteContents();
					var el = document.createElement("div");
					el.innerHTML = html;
					var frag = document.createDocumentFragment(), node, lastNode;
					while (node = el.firstChild) {
						lastNode = frag.appendChild(node)
					}
					range.insertNode(frag);
					if (lastNode) {
						range = range.cloneRange();
						range.setStartAfter(lastNode);
						range.collapse(true);
						sel.removeAllRanges();
						sel.addRange(range)
					}
				}
			} else if (document.selection && document.selection.type !== "Control") {
				document.selection.createRange().pasteHTML(html)
			}
		}

		function changeAttributeQuotes(string) {
			if (!string) {
				return ""
			}
			var regex;
			var rstring;
			if (settings.useSingleQuotes === true) {
				regex = /\s+(\w+\s*=\s*(["][^"]*["])|(['][^']*[']))+/g;
				rstring = string.replace(regex, function ($0, $1, $2) {
					if (!$2) {
						return $0
					}
					return $0.replace($2, $2.replace(/\"/g, "'"))
				})
			} else {
				regex = /\s+(\w+\s*=\s*(['][^']*['])|(["][^"]*["]))+/g;
				rstring = string.replace(regex, function ($0, $1, $2) {
					if (!$2) {
						return $0
					}
					return $0.replace($2, $2.replace(/'/g, '"'))
				})
			}
			return rstring
		}

		function loadColors(command) {
			var colors = [];
			var result = "";
			colors["#FFFFFF"] = settings.translations.white;
			colors["#000000"] = settings.translations.black;
			colors["#7F6000"] = settings.translations.brown;
			colors["#938953"] = settings.translations.beige;
			colors["#1F497D"] = settings.translations.darkBlue;
			colors["blue"] = settings.translations.blue;
			colors["#4F81BD"] = settings.translations.lightBlue;
			colors["#953734"] = settings.translations.darkRed;
			colors["red"] = settings.translations.red;
			colors["#4F6128"] = settings.translations.darkGreen;
			colors["green"] = settings.translations.green;
			colors["#3F3151"] = settings.translations.purple;
			colors["#31859B"] = settings.translations.darkTurquois;
			colors["#4BACC6"] = settings.translations.turquois;
			colors["#E36C09"] = settings.translations.darkOrange;
			colors["#F79646"] = settings.translations.orange;
			colors["#FFFF00"] = settings.translations.yellow;
			if (settings.colors && settings.colors.length > 0) {
				colors = settings.colors
			}
			for (var i in colors) {
				result += '<li class="inline"><a data-command="' + command + '" data-option="' + i + '" style="text-align:left;" title="' + colors[i] + '"><span class="box-color" style="background-color:' + i + '"></span></a></li>'
			}
			return result
		}

		function toggleCode(editorID) {
			doRestore(editorID);
			if ($editor.find(".richText-editor").is(":visible")) {
				$editor.find(".richText-initial").show();
				$editor.find(".richText-editor").hide();
				$(".richText-toolbar").find(".richText-btn").each(function () {
					if ($(this).children(".fa-code").length === 0) {
						$(this).parent("li").attr("data-disable", "true")
					}
				});
				convertCaretPosition(editorID, savedSelection)
			} else {
				$editor.find(".richText-initial").hide();
				$editor.find(".richText-editor").show();
				convertCaretPosition(editorID, savedSelection, true);
				$(".richText-toolbar").find("li").removeAttr("data-disable")
			}
		}

		function convertCaretPosition(editorID, selection, reverse) {
			var $editor = $("#" + editorID);
			var $textarea = $editor.siblings(".richText-initial");
			var code = $textarea.val();
			if (!selection || !code) {
				return {start: 0, end: 0}
			}
			if (reverse === true) {
				savedSelection = {start: $editor.text().length, end: $editor.text().length, editorID: editorID};
				restoreSelection(editorID);
				return true
			}
			selection.node = $textarea[0];
			var states = {
				start: false,
				end: false,
				tag: false,
				isTag: false,
				tagsCount: 0,
				isHighlight: selection.start !== selection.end
			};
			for (var i = 0; i < code.length; i++) {
				if (code[i] === "<") {
					states.isTag = true;
					states.tag = false;
					states.tagsCount++
				} else if (states.isTag === true && code[i] !== ">") {
					states.tagsCount++
				} else if (states.isTag === true && code[i] === ">") {
					states.isTag = false;
					states.tag = true;
					states.tagsCount++
				} else if (states.tag === true) {
					states.tag = false
				}
				if (!reverse) {
					if (selection.start + states.tagsCount <= i && states.isHighlight && !states.isTag && !states.tag && !states.start) {
						selection.start = i;
						states.start = true
					} else if (selection.start + states.tagsCount <= i + 1 && !states.isHighlight && !states.isTag && !states.tag && !states.start) {
						selection.start = i + 1;
						states.start = true
					}
					if (selection.end + states.tagsCount <= i + 1 && !states.isTag && !states.tag && !states.end) {
						selection.end = i + 1;
						states.end = true
					}
				}
			}
			createSelection(selection.node, selection.start, selection.end);
			return selection
		}

		function createSelection(field, start, end) {
			if (field.createTextRange) {
				var selRange = field.createTextRange();
				selRange.collapse(true);
				selRange.moveStart("character", start);
				selRange.moveEnd("character", end);
				selRange.select();
				field.focus()
			} else if (field.setSelectionRange) {
				field.focus();
				field.setSelectionRange(start, end)
			} else if (typeof field.selectionStart != "undefined") {
				field.selectionStart = start;
				field.selectionEnd = end;
				field.focus()
			}
		}

		function getVideoCode(url, size) {
			var video = getVideoID(url);
			var responsive = false, success = false;
			if (!video) {
				return false
			}
			if (!size) {
				size = "640x360";
				size = size.split("x")
			} else if (size !== "responsive") {
				size = size.split("x")
			} else {
				responsive = true;
				size = "640x360";
				size = size.split("x")
			}
			var html = "<br><br>";
			if (responsive === true) {
				html += '<div class="videoEmbed" style="position:relative;height:0;padding-bottom:56.25%">'
			}
			var allowfullscreen = "webkitallowfullscreen mozallowfullscreen allowfullscreen";
			if (video.platform === "YouTube") {
				var youtubeDomain = settings.youtubeCookies ? "www.youtube.com" : "www.youtube-nocookie.com";
				html += '<iframe src="https://' + youtubeDomain + "/embed/" + video.id + '?ecver=2" width="' + size[0] + '" height="' + size[1] + '" frameborder="0"' + (responsive === true ? ' style="position:absolute;width:100%;height:100%;left:0"' : "") + " " + allowfullscreen + "></iframe>";
				success = true
			} else if (video.platform === "Vimeo") {
				html += '<iframe src="https://player.vimeo.com/video/' + video.id + '" width="' + size[0] + '" height="' + size[1] + '" frameborder="0"' + (responsive === true ? ' style="position:absolute;width:100%;height:100%;left:0"' : "") + " " + allowfullscreen + "></iframe>";
				success = true
			} else if (video.platform === "Facebook") {
				html += '<iframe src="https://www.facebook.com/plugins/video.php?href=' + encodeURI(url) + "&show_text=0&width=" + size[0] + '" width="' + size[0] + '" height="' + size[1] + '" style="' + (responsive === true ? 'position:absolute;width:100%;height:100%;left:0;border:none;overflow:hidden"' : "border:none;overflow:hidden") + '" scrolling="no" frameborder="0" allowTransparency="true" ' + allowfullscreen + "></iframe>";
				success = true
			} else if (video.platform === "Dailymotion") {
				html += '<iframe frameborder="0" width="' + size[0] + '" height="' + size[1] + '" src="//www.dailymotion.com/embed/video/' + video.id + '"' + (responsive === true ? ' style="position:absolute;width:100%;height:100%;left:0"' : "") + " " + allowfullscreen + "></iframe>";
				success = true
			}
			if (responsive === true) {
				html += "</div>"
			}
			html += "<br><br>";
			if (success) {
				return html
			}
			return false
		}

		function getVideoID(url) {
			var vimeoRegExp = /(?:http?s?:\/\/)?(?:www\.)?(?:vimeo\.com)\/?(.+)/;
			var youtubeRegExp = /^.*(youtu.be\/|v\/|u\/\w\/|embed\/|watch\?v=|\&v=)([^#\&\?]*).*/;
			var facebookRegExp = /(?:http?s?:\/\/)?(?:www\.)?(?:facebook\.com)\/.*\/videos\/[0-9]+/;
			var dailymotionRegExp = /(?:http?s?:\/\/)?(?:www\.)?(?:dailymotion\.com)\/video\/([a-zA-Z0-9]+)/;
			var youtubeMatch = url.match(youtubeRegExp);
			var vimeoMatch = url.match(vimeoRegExp);
			var facebookMatch = url.match(facebookRegExp);
			var dailymotionMatch = url.match(dailymotionRegExp);
			if (youtubeMatch && youtubeMatch[2].length === 11) {
				return {platform: "YouTube", id: youtubeMatch[2]}
			} else if (vimeoMatch && vimeoMatch[1]) {
				return {platform: "Vimeo", id: vimeoMatch[1]}
			} else if (facebookMatch && facebookMatch[0]) {
				return {platform: "Facebook", id: facebookMatch[0]}
			} else if (dailymotionMatch && dailymotionMatch[1]) {
				return {platform: "Dailymotion", id: dailymotionMatch[1]}
			}
			return false
		}

		function fixFirstLine() {
			if ($editor && !$editor.find(".richText-editor").html()) {
				if (settings.useParagraph !== false) {
					$editor.find(".richText-editor").html("<p><br></p>")
				} else {
					$editor.find(".richText-editor").html("<div><br></div>")
				}
			} else {
				if (settings.useParagraph !== false) {
					$editor.find(".richText-editor").find("div:not(.videoEmbed)").replaceWith(function () {
						return $("<p />", {html: $(this).html()})
					})
				} else {
					$editor.find(".richText-editor").find("p").replaceWith(function () {
						return $("<div />", {html: $(this).html()})
					})
				}
			}
			updateTextarea()
		}

		return $(this)
	};
	$.fn.unRichText = function (options) {
		var settings = $.extend({delay: 0}, options);
		var $editor, $textarea, $main;
		var $el = $(this);

		function init() {
			if ($el.hasClass("richText")) {
				$main = $el
			} else if ($el.hasClass("richText-initial") || $el.hasClass("richText-editor")) {
				$main = $el.parents(".richText")
			}
			if (!$main) {
				return false
			}
			$editor = $main.find(".richText-editor");
			$textarea = $main.find(".richText-initial");
			if (parseInt(settings.delay) > 0) {
				setTimeout(remove, parseInt(settings.delay))
			} else {
				remove()
			}
		}

		init();

		function remove() {
			$main.find(".richText-toolbar").remove();
			$main.find(".richText-editor").remove();
			$textarea.unwrap(".richText").data("editor", "richText").removeClass("richText-initial").show();
			if (settings.callback && typeof settings.callback === "function") {
				settings.callback()
			}
		}
	}
})(jQuery);

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
	$('.sidenav').sidenav();

	// text formatting
	$('#article').richText({
		bold: true,
		italic: true,
		underline: true,
		ol: true,
		ul: true,
		heading: true,
		useParagraph: false,
		urls: false,
		removeStyles: false,
		videoEmbed: false,
		fontColor: true,
		leftAlign: false,
		centerAlign: false,
		rightAlign: false,
		justify: false,
		fonts: false,
		fontSize: false,
		imageUpload: false,
		fileUpload: false,
		table: false,
		code: false,
		fileHTML: '',
		imageHTML: '',
		maxlength: 5000,
		id: 'articleRich',
		class: 'hide'
	});

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

function uploadPhoto() {
	if (typeof apretaste.loadImage != 'undefined') {
		apretaste.loadImage('onImageLoaded')
	} else {
		loadFileToBase64();
	}
};

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

var commentToDelete = null;

function openDeleteModal(commentId) {
	var modalContent = $('#deleteModal > .modal-content');

	if (commentId != null) {
		commentToDelete = commentId;
		modalContent.html('¿Est&aacute;s seguro de eliminar este comentario?');
	} else {
		commentId = null;
		modalContent.html('¿Est&aacute;s seguro de eliminar esta nota?');
	}

	M.Modal.getInstance($('#deleteModal')).open();
}

function addArticleText() {
	$('#articleTarget').html($('#article').val()).removeClass('hide');
}

function replyUser(user) {
	user = user + ' '
	var comment = $('#comment');
	var currentComment = comment.val();

	if (currentComment.length === 0) comment.val('@' + user);
	else comment.val(currentComment + ' @' + user);
	M.Modal.getInstance($('#newCommentModal')).open();
	comment.focus();
}

function appendTag(tag) {
	tag = tag + ' '
	var content = $('#note');
	var currentContent = content.val();

	if (currentContent.length === 0) content.val('#' + tag);
	else content.val(currentContent + ' #' + tag);

	content.focus();
}

function openNote(id) {
	apretaste.send({'command': 'PIZARRA NOTA', 'data': {'note': id}});
}

var activeNote;

function sendNote() {
	hideKeyboard();
	var note = $('#note').val().trim();
	note = '' + note.replace(/ +(?= )/g, '')

	if (note.length > 2 || notePicture != null || notePicturePath != null) {
		var files = notePicturePath != null ? [notePicturePath] : [];
		var basename = notePicturePath != null ? notePicturePath.split(/[\\/]/).pop() : null;

		apretaste.send({
			'command': 'PIZARRA ESCRIBIR',
			'data': {
				'text': note,
				'image': notePicture,
				'imageName': basename,
				'article': ($($('#article').val()).text()) ? encode_utf8($('#article').val()) : '',
			},
			'files': files,
			'redirect': false,
			'callback': {
				'name': 'sendNoteCallback',
				'data': note
			}
		});
	} else {
		showToast('Mínimo tres caracteres');
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

var share = {
	send: null
}

function initShare(note) {
	var hasImage = note.image !== null && note.image !== '';
	var onlyImage = hasImage && note.text === '';
	var icon = hasImage ? 'image' : 'clipboard-list';

	var text = onlyImage ? 'Nota con imagen de @' + note.username : '@' + note.username + ': ' + note.text;

	text = text.substr(0, 100);

	$('#shareIcon').addClass('fa-' + icon);
	$('#shareText').html(text);

	share = {
		text: text,
		icon: icon,
		send: function () {
			apretaste.send({
				command: 'PIZARRA PUBLICAR',
				redirect: false,
				callback: {
					name: 'shareNoteCallback',
					data: note.id
				},
				data: {
					text: $('#shareMessage').val(),
					image: '',
					action: 'pizarra-share',
					link: {
						command: btoa(JSON.stringify({
							command: 'PIZARRA NOTA',
							data: {note: note.id}
						})),
						icon: share.icon,
						text: share.text
					}
				}
			})
		}
	};
}

function shareNoteCallback(noteId){
	showToast('Has reposteado una nota');
	$('#shareMessage').val('')
}

function openReportModal() {
	M.Modal.getInstance($('#reportModal')).open();
	$('#reportMessage').focus();
}

function openShareModal(noteId) {
	var note = this.note;

	if (typeof note == 'undefined') {
		note = notes.filter(function (n) {
			return n.id === noteId
		})[0];
	}

	initShare(note)
	M.Modal.getInstance($('#shareModal')).open();
	$('#shareMessage').focus();
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
				search: search
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
			search: '#' + topic
		}
	});
}

function searchUsername(username) {
	apretaste.send({
		command: 'pizarra global',
		data: {
			search: '@' + username
		}
	});
}

function deleteNote(id) {
	if(commentToDelete != null){
		apretaste.send({
			'command': 'PIZARRA ELIMINAR',
			'data': {
				'comment': commentToDelete
			},
			'redirect': false,
			callback: {
				'name': 'deleteCommentCallback',
				'data': commentToDelete
			}
		});

		return;
	}

	apretaste.send({
		'command': 'PIZARRA ELIMINAR',
		'data': {
			'note': id,
		},
		'redirect': false,
		callback: {
			'name': 'deleteCallback',
			'data': id
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

function nextPage() {
	var command = title === 'Global' ? 'pizarra global' : 'pizarra';
	apretaste.send({
		command: command,
		data: {
			search: typeof search != 'undefined' ? search : null,
			page: page + 1
		}
	});
}

function previousPage() {
	var command = title === 'Global' ? 'pizarra global' : 'pizarra';
	apretaste.send({
		command: command,
		data: {
			search: typeof search != 'undefined' ? search : null,
			page: page - 1
		}
	});
}

function deleteCallback(id) {
	apretaste.send({command: 'PIZARRA'});
}

function deleteCommentCallback(id) {
	$('#comments #' + id + ' .text').html('Comentario eliminado');
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

// submit the profile informacion


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

function like(id, type, pubType) {
	if (pubType === undefined || typeof pubType == 'undefined') {
		pubType = 'note';
	}

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
		'showLoading': false,
		'redirect': false
	});

	likeCallback({
		'id': id,
		'type': type,
		'pubType': pubType
	});
}

function likeCallback(data) {
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

var currentUser = null;
var currentUsername = null;

function addFriendModalOpen(id, username) {
	currentUser = id;
	currentUsername = username;
	$('.username').html('@' + username);
	M.Modal.getInstance($('#addFriendModal')).open();
}

function addFriend() {
	apretaste.send({
		command: 'amigos agregar',
		data: {id: currentUser},
		redirect: false,
		callback: {
			name: 'addFriendCallback'
		}
	});
}

function addFriendCallback() {
	showToast('Amistad aceptada');

	$('#' + currentUser + ' .action').html(
		'<a class="secondary-content second">' +
		'    <i class="fa fa-comment"' +
		'       onclick="openChat(\'' + currentUser + '\')">' +
		'    </i>' +
		'</a>' +
		'<a class="secondary-content third">' +
		'    <i class="fa fa-ban red-text"' +
		'       onclick="deleteModalOpen(\'' + currentUser + '\', \'' + currentUsername + '\')">' +
		'    </i>' +
		'</a>');
}

function deleteModalOpen(id, username) {
	currentUser = id;
	currentUsername = username;
	$('.username').html('@' + username);
	M.Modal.getInstance($('#deleteModal')).open();
}

function deleteFriend() {
	apretaste.send({
		command: 'amigos eliminar',
		data: {id: currentUser},
		redirect: false,
		callback: {
			name: 'deleteFriendCallback',
		}
	});
}

function deleteFriendCallback() {
	showToast('Amigo eliminado');

	$('#' + currentUser + ' .action').html(
		'<a class="secondary-content second">' +
		'    <i class="fa fa-user-plus green-text"' +
		'       onclick="addFriendModalOpen(\'' + currentUser + '\', \'' + currentUsername + '\')">' +
		'    </i>' +
		'</a>');
}

function openChat(id) {
	apretaste.send({
		command: 'chat',
		data: {
			id: id
		}
	});
}


// Callback functions

function sendCommentCallback(comment) {
	var avatar = 'face="' + myUser.avatar + '"';
	if (myUser.isInfluencer) {
		var serviceImgPath = $('serviceImgPath').attr('data');
		avatar += ' creator_image="' + serviceImgPath + myUser.username + '.png" state="gold"'
	}

	var element =
		"<li class=\"right\" id=\"last\">\n" +
		"    <div class=\"person-avatar circle\" " + avatar + " color=\"" + myUser.avatarColor + "\"\n" +
		"         size=\"30\" onclick=\"openProfile('" + myUser.username + "')\"></div>\n" +
		"    <div class=\"head\">\n" +
		"        <a onclick=\"openProfile('" + myUser.username + "')\"\n" +
		"           class=\"" + myUser.gender + "\">@" + myUser.username + "</a>\n" +
		"        <span class=\"date\">" + moment().format('MMM D, YYYY h:mm A') + "</span>\n" +
		"    </div>\n" +
		"    <span class=\"text\" style=\"word-break: break-word;\">" + comment + "</span>\n" +
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

	apretaste.send({
		command: 'PIZARRA NOTA',
		data: {
			note: 'last'
		}
	});
	return;
	var serviceImgPath = $('serviceImgPath').attr('data');
	var topics = note.match(/(^|\B)#(?![0-9_]+\b)([a-zA-Z0-9_]{1,30})(\b|\r)/g);
	var htmlTopics = "";
	topics = topics != null ? topics.splice(0, 3) : [myUser.topic];

	var hasImage = "";
	if (notePicture != null || notePicturePath != null) {
		var src = "data:image/jpg;base64," + notePicture;
		if (notePicturePath != null) src = "file://" + notePicturePath;

		if (typeof apretaste.showImage != 'undefined' && notePicturePath != null) {
			hasImage = "<img class=\"responsive-img\" style=\"width: 100%\" src=\"" + src + "\" onclick=\"apretaste.showImage('" + src + "')\">";
		} else {
			hasImage = "<img class=\"responsive-img\" style=\"width: 100%\" src=\"" + src + "\" onclick=\"apretaste.send({'command': 'PIZARRA NOTA','data':{'note':'last'}});\">";
		}

		// clean the image
		$('#notePicture').remove();
		notePicture = null;
		notePicturePath = null;
	}

	var avatar = 'face="' + myUser.avatar + '"';
	if (myUser.isInfluencer) {
		avatar += ' creator_image="' + serviceImgPath + myUser.username + '.png" state="gold"'
	}

	topics.forEach(function (topic) {
		topic = topic.replace('#', '');
		htmlTopics +=
			'<div class="chip small" onclick="apretaste.send({\'command\': \'PIZARRA GLOBAL\',\'data\':{\'search\':\'#' + topic + '\'}})">' +
			'    <i class="fa fa-hashtag"></i>' + topic +
			'</div>';
	});
	note = note.escapeHTML();

	var article = ($($('#article').val()).text()) ?
		'<ul class="collection one-line preview">\n' +
		'                    <li class="collection-item avatar">\n' +
		'                        <i class="fas fa-file-word material-icons circle"></i>\n' +
		'                        <span class="title">Esta nota viene con un texto adjunto</span>\n' +
		'                    </li>\n' +
		'                </ul>\n' : '';

	// clean the article
	$('#article').val('').trigger('change');
	$('#articleTarget').html('').addClass('hide');

	var element =
		'<div class="card note" id="last" liked="false"\n' +
		'                 unliked="false">\n' +
		'                <div class="card-person grey lighten-5">\n' +
		'                        <div class="person-avatar circle left"\n' + avatar +
		'                             color="' + myUser.avatarColor + '"\n' +
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
		'                    <p><b>' + note + '</b></p>\n' +
		article +
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

	var avatarElement = $('#last .person-avatar');
	avatarElement.innerHTML = '';
	setElementAsAvatar(avatarElement);

	$('html, body').animate({
		scrollTop: $("#last").offset().top
	}, 1000);
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

var notePicture = null;
var notePicturePath = null;

function onImageLoaded(path) {
	showLoadedImage(path);
	notePicturePath = path;
}

function showLoadedImage(source) {
	if ($('#notePicture').length === 0) {
		$('#writeModal > .row > .col').append('<img id="notePicture" class="responsive-img"/>');
	}

	$('#notePicture').attr('src', source);
}

function sendFile(base64File) {
	notePicture = base64File;
	var notePictureSrc = "data:image/jpg;base64," + base64File;

	showLoadedImage(notePictureSrc)
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

// Convert links in text
window.linkify = (function () {
	var
		SCHEME = "[a-z\\d.-]+://",
		IPV4 = "(?:(?:[0-9]|[1-9]\\d|1\\d{2}|2[0-4]\\d|25[0-5])\\.){3}(?:[0-9]|[1-9]\\d|1\\d{2}|2[0-4]\\d|25[0-5])",
		HOSTNAME = "(?:(?:[^\\s!@#$%^&*()_=+[\\]{}\\\\|;:'\",.<>/?]+)\\.)+",
		TLD = "(?:ac|ad|aero|ae|af|ag|ai|al|am|an|ao|aq|arpa|ar|asia|as|at|au|aw|ax|az|ba|bb|bd|be|bf|bg|bh|biz|bi|bj|bm|bn|bo|br|bs|bt|bv|bw|by|bz|cat|ca|cc|cd|cf|cg|ch|ci|ck|cl|cm|cn|coop|com|co|cr|cu|cv|cx|cy|cz|de|dj|dk|dm|do|dz|ec|edu|ee|eg|er|es|et|eu|fi|fj|fk|fm|fo|fr|ga|gb|gd|ge|gf|gg|gh|gi|gl|gm|gn|gov|gp|gq|gr|gs|gt|gu|gw|gy|hk|hm|hn|hr|ht|hu|id|ie|il|im|info|int|in|io|iq|ir|is|it|je|jm|jobs|jo|jp|ke|kg|kh|ki|km|kn|kp|kr|kw|ky|kz|la|lb|lc|li|lk|lr|ls|lt|lu|lv|ly|ma|mc|md|me|mg|mh|mil|mk|ml|mm|mn|mobi|mo|mp|mq|mr|ms|mt|museum|mu|mv|mw|mx|my|mz|name|na|nc|net|ne|nf|ng|ni|nl|no|np|nr|nu|nz|om|org|pa|pe|pf|pg|ph|pk|pl|pm|pn|pro|pr|ps|pt|pw|py|qa|re|ro|rs|ru|rw|sa|sb|sc|sd|se|sg|sh|si|sj|sk|sl|sm|sn|so|sr|st|su|sv|sy|sz|tc|td|tel|tf|tg|th|tj|tk|tl|tm|tn|to|tp|travel|tr|tt|tv|tw|tz|ua|ug|uk|um|us|uy|uz|va|vc|ve|vg|vi|vn|vu|wf|ws|xn--0zwm56d|xn--11b5bs3a9aj6g|xn--80akhbyknj4f|xn--9t4b11yi5a|xn--deba0ad|xn--g6w251d|xn--hgbk6aj7f53bba|xn--hlcj6aya9esc7a|xn--jxalpdlp|xn--kgbechtv|xn--zckzah|ye|yt|yu|za|zm|zw)",
		HOST_OR_IP = "(?:" + HOSTNAME + TLD + "|" + IPV4 + ")",
		PATH = "(?:[;/][^#?<>\\s]*)?",
		QUERY_FRAG = "(?:\\?[^#<>\\s]*)?(?:#[^<>\\s]*)?",
		URI1 = "\\b" + SCHEME + "[^<>\\s]+",
		URI2 = "\\b" + HOST_OR_IP + PATH + QUERY_FRAG + "(?!\\w)",

		MAILTO = "mailto:",
		EMAIL = "(?:" + MAILTO + ")?[a-z0-9!#$%&'*+/=?^_`{|}~-]+(?:\\.[a-z0-9!#$%&'*+/=?^_`{|}~-]+)*@" + HOST_OR_IP + QUERY_FRAG + "(?!\\w)",

		URI_RE = new RegExp("(?:" + URI1 + "|" + URI2 + "|" + EMAIL + ")", "ig"),
		SCHEME_RE = new RegExp("^" + SCHEME, "i"),

		quotes = {
			"'": "`",
			'>': '<',
			')': '(',
			']': '[',
			'}': '{',
			'»': '«',
			'›': '‹'
		},

		default_options = {
			callback: function (text, href) {
				return href ? '<a href="' + href + '" title="' + href + '">' + text + '</a>' : text;
			},
			punct_regexp: /(?:[!?.,:;'"]|(?:&|&amp;)(?:lt|gt|quot|apos|raquo|laquo|rsaquo|lsaquo);)$/
		};

	return function (txt, options) {
		options = options || {};

		// Temp variables.
		var arr,
			i,
			link,
			href,

			// Output HTML.
			html = '',

			// Store text / link parts, in order, for re-combination.
			parts = [],

			// Used for keeping track of indices in the text.
			idx_prev,
			idx_last,
			idx,
			link_last,

			// Used for trimming trailing punctuation and quotes from links.
			matches_begin,
			matches_end,
			quote_begin,
			quote_end;

		// Initialize options.
		for (i in default_options) {
			if (options[i] === undefined) {
				options[i] = default_options[i];
			}
		}

		// Find links.
		while (arr = URI_RE.exec(txt)) {

			link = arr[0];
			idx_last = URI_RE.lastIndex;
			idx = idx_last - link.length;

			// Not a link if preceded by certain characters.
			if (/[\/:]/.test(txt.charAt(idx - 1))) {
				continue;
			}

			// Trim trailing punctuation.
			do {
				// If no changes are made, we don't want to loop forever!
				link_last = link;

				quote_end = link.substr(-1)
				quote_begin = quotes[quote_end];

				// Ending quote character?
				if (quote_begin) {
					matches_begin = link.match(new RegExp('\\' + quote_begin + '(?!$)', 'g'));
					matches_end = link.match(new RegExp('\\' + quote_end, 'g'));

					// If quotes are unbalanced, remove trailing quote character.
					if ((matches_begin ? matches_begin.length : 0) < (matches_end ? matches_end.length : 0)) {
						link = link.substr(0, link.length - 1);
						idx_last--;
					}
				}

				// Ending non-quote punctuation character?
				if (options.punct_regexp) {
					link = link.replace(options.punct_regexp, function (a) {
						idx_last -= a.length;
						return '';
					});
				}
			} while (link.length && link !== link_last);

			href = link;

			// Add appropriate protocol to naked links.
			if (!SCHEME_RE.test(href)) {
				href = (href.indexOf('@') !== -1 ? (!href.indexOf(MAILTO) ? '' : MAILTO)
					: !href.indexOf('irc.') ? 'irc://'
						: !href.indexOf('ftp.') ? 'ftp://'
							: 'http://')
					+ href;
			}

			// Push preceding non-link text onto the array.
			if (idx_prev != idx) {
				parts.push([txt.slice(idx_prev, idx)]);
				idx_prev = idx_last;
			}

			// Push massaged link onto the array
			parts.push([link, href]);
		}
		;

		// Push remaining non-link text onto the array.
		parts.push([txt.substr(idx_prev)]);

		// Process the array items.
		for (i = 0; i < parts.length; i++) {
			html += options.callback.apply(window, parts[i]);
		}

		// In case of catastrophic failure, return the original text;
		return themify(html || txt);
	};

})();

function themify(text){
	var topics = text.match(/(^|\B)#(?![0-9_]+\b)([a-zA-Z0-9_]{1,30})(\b|\r)/g);

	if(topics !== null){
		topics.forEach(function (topic) {
			text = text.replaceAll(topic,
				'<a onclick="apretaste.send({\'command\': \'PIZARRA GLOBAL\',\'data\':{\'search\':\'' + topic + '\'}})">' +
				topic +
				'</a>'
			);
		});
	}

	return text;
}

function encode_utf8(s) {
	return unescape(encodeURIComponent(s));
}
