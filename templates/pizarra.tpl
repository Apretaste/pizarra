{include file="../includes/appmenu.tpl"}

{if $isProfileIncomplete}
	<div class="message error">Para usar pizarra al m&aacute;ximo, {link href="PERFIL EDITAR" caption="complete su perfil"}</div>
{/if}

<div id="newNoteOk" class="message success hidden">Su nota ha sido publicada correctamente</div>

<table width="100%">
	<tr>
		<td><h1 style="margin-bottom:0px;">{$title}</h1></td>
		<td align="right" valign="top">
			<nobr>
			{button href="PIZARRA ESCRIBIR" desc="a:Escriba una nota*" caption="&#10010; Escribir" size="small" popup="true" wait="false" callback="addNote"}
			{button href="PIZARRA" caption="🔍" size="icon" color="grey" popup="true" desc="Escriba un texto, @username o #tema*"}
			</nobr>
		</td>
	</tr>
	<tr>
		<td colspan="2" style="font-size:small;">
			<span style="color:gray;">Temas populares:</span>
			{foreach from=$popularTopics item=topic}
				{link href="PIZARRA {$topic->name}" caption="#{$topic->name}" style="color:gray;"}&nbsp;
			{/foreach}
		</td>
	</tr>
</table>

{foreach from=$notes item=note}
	{assign var="bgcolor" value="white"}
	{if $note@iteration is even}{assign var="bgcolor" value="grey lighten-3"}{/if}

	{assign var="color" value="black-text"}
	{if $note['gender'] eq "M"}{assign var="color" value="blue-text"}{/if}
	{if $note['gender'] eq "F"}{assign var="color" value="pink-text"}{/if}

<div class="row">
	<div class="card {$bgcolor}">
		<div class="card-content">
			<span class="{$color}">{link href="PIZARRA PERFIL @{$note['username']}" caption="@{$note['username']}"}</span>&middot;
			<small class="grey-text">{$note['location']}</small>&middot;
			<small class="grey-text">{$note['inserted']|date_format:"%b %e, %I:%M %p"|capitalize}</small>
			{if $note['online']}&nbsp;&nbsp;<span class="online">ONLINE</span>{/if}
			<div class="divider"></div>
			{space5}
			<p style="word-break: break-word;">{$note['text']|replace_url}</p>
			{space5}
			<small>
				{foreach from=$note['topics'] item=topic}
					{link href="PIZARRA {$topic['name']}" caption="#{$topic['name']}" style="color:gray;"}&nbsp;
				{/foreach}
			</small>
		</div>
		<div class="card-action">
			<span class="emoji">{button id="like{$note@iteration}" href="PIZARRA LIKE {$note['id']}" caption="&#128077; <span id='likecounter{$note@iteration}'>{$note['likes']}</span>" wait="false" callback="update:like:{$note@iteration}"}</span>&nbsp;&nbsp;&nbsp;&nbsp;
			<span class="emoji">{button id="unlike{$note@iteration}" href="PIZARRA UNLIKE {$note['id']}" caption="&#x1F44E; <span id='unlikecounter{$note@iteration}'>{$note['unlikes']}</span>" wait="false" callback="update:unlike:{$note@iteration}"}</span>&nbsp;&nbsp;
			<span class="emoji">{link href="PIZARRA NOTA {$note['id']}" caption="&#128172; {$note['comments']}"}</span>&nbsp;&nbsp;&nbsp;&nbsp;
			{if $note['canmodify']}
				{if count($note['topics']) < 3}
					<span><b>{link href="PIZARRA TEMIFICAR {$note['id']}" popup="true" wait="false" caption="#" desc="A que #tema pertenece esta nota?*"}</b></span>&nbsp;&nbsp;&nbsp;&nbsp;
				{/if}
				<span class="emoji">{link href="PIZARRA ELIMINAR {$note['id']}" caption="❌" wait="true"}</span>
			{/if}
		</div>
	</div>
</div>
{/foreach}

<script>
	function addNote(values) { document.getElementById('newNoteOk').style.display = "block"; }
	function update(values) { 
		document.getElementById(values[0]+values[1]).style.color = "red";
		var counter = document.getElementById(values[0]+'counter'+values[1]).innerHTML;
		document.getElementById(values[0]+'counter'+values[1]).innerHTML = ++counter;
	}
</script>
