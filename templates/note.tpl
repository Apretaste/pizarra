{space5}
	<font color="gray">
		<small>
			<font color="orange">{if $note['friend']}&#8619;{/if}</font>
			{link href="PERFIL @{$note['username']}" caption="@{$note['username']}"},
			{$note['location']},
			{if $note['gender'] eq "M"}<font color="#4863A0">M</font>{/if}
			{if $note['gender'] eq "F"}<font color=#F778A1>F</font>{/if}
			{if $note['picture']}[foto]{/if}
			{separator}
			<font color="gray">{$note['inserted']|date_format:"%e/%m %l:%M %p"}</font>
			{separator}
			{link href="PIZARRA BLOQUEAR @{$note['username']}" caption="&#10006; Quitar" body="Envie este email para bloquear a @{$note['username']} en tu Pizarra."}
		</small>
	</font>
	<br/>
	<big><big>{$note['text']|replace_url}</big></big>
	<br/>
	<small>
		{link href="PIZARRA LIKE {$note['id']}" caption="Bueno" body="Envie este email tal como esta para expresar gusto por este post de este usuario"}
		[<font color="red">{$note['likes']}&#9786;</font>]
		{separator}
		{link href="PIZARRA UNLIKE {$note['id']}" caption="Malo" body="Envie este email tal como esta para expresar que este post no le gusta"}
		[<font color="black">{$note['unlikes']}&#9785;</font>]
	
	</small>
	{space5}

<table width="100%">
{foreach from=$note['comments'] item=comment}
	<tr>
	<td width="30"></td>
		<td>
			{space5}
			<font color="gray">
				<small>
					{link href="PERFIL @{$comment->username}" caption="@{$comment->username}"},
					{if $comment->gender eq "M"}<font color="#4863A0">M</font>{/if}
					{if $comment->gender eq "F"}<font color=#F778A1>F</font>{/if}
					{if $comment->picture}[foto]{/if}
					{separator}
					<font color="gray">{$comment->inserted|date_format:"%e/%m %l:%M %p"}</font>
				</small>
			</font>
			<br/>
			{$comment->text|replace_url}
			<br/>
			{space5}
		</td>
	</tr>
{/foreach}
</table>
<center>{button href="PIZARRA {$note['id']}* Escriba aqui su comentario a la nota" caption="Comentar"}</center>