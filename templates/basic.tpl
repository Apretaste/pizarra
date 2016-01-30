{if $isProfileIncomplete}
<table width="100%">
	<tr>
		<td align="center" bgcolor="#F6CED8">
			<p><small>Para usar pizarra al m&aacute;ximo, {link href="PERFIL EDITAR" caption="complete su perfil"}.</small></p>
		</td>
	</tr>
</table>
{/if}

<table width="100%">
	<tr>
		<td><h1>&Uacute;ltimas 50 notas</h1></td>
		<td align="right" valign="top">
			{button href="PIZARRA reemplace este texto por su nota" body="Escriba una nota que no exeda los 130 caracteres en el asunto y envie este email" caption="&#10010; Nueva nota"}
		</td>
	</tr>
</table>

<table width="100%">
{foreach from=$notes item=note}
	<tr {if $note@iteration is even}bgcolor="#F2F2F2"{/if}>
		<td>
			{space5}
			<font color="gray">
				<small>
					{if $note['source'] eq "apretaste"}
						{link href="PERFIL @{$note['name']}" caption="@{$note['name']}"},
						{if $note['gender'] eq "M"}<font color="#4863A0">Var&oacute;n</font>,{/if}
						{if $note['gender'] eq "F"}<font color=#F778A1>Mujer</font>,{/if}
						{if $note['picture'] eq 1}[foto],{/if}
						{$note['location']}
					{/if}
		
					{if $note['source'] eq "twitter"}
						{$note['name']}, {$note['location']}
					{/if}

					{separator}
					{$note['inserted']|date_format:"%e/%m %I:%M %p"}
				</small>
			</font>
			<br/>
			<big><big>{$note['text']}</big></big>
			<br/>
			<small>
				{if $email neq $note['email'] and $note['source'] eq "apretaste"}
					{link href="PIZARRA LIKE {$note['id']}" caption="&hearts; Me gusta" body="Envie este email tal como esta para expresar gusto por este post de este usuario"}
					[<font color="red">{$note['likes']}&hearts;</font>]
					{separator}
					{link href="NOTA @{$note['name']} Reemplace este texto por su nota" caption="&#x2605; Charlar" body="Escriba en el asunto la nota que le llegara a @{$note['name']} y envie este email."}
					{separator}
					{link href="PIZARRA REPORTAR @{$note['name']}" caption="Reportar" body="Envie este email para reportar a @{$note['name']} como grosero o de mal gusto. Sea tolerante. Muchos usuarios escriben sobre su credo, orientacion sexual, pensamiento politico, diferencia racial o cultural, lo cual no significa que sus notas sean de mal gusto solo porque otros no esten de acuerdo."}
				{/if}
			</small>
			{space5}
		</td>
	</tr>
{/foreach}
</table>

{space30}

<center>
	<p><small>&iquest;Extra&ntilde;as a tus amigos? {link href="INVITAR su@amigo.cu" caption="Inv&iacute;talos" body="Cambie en el asunto su@amigo.cu por el email de la persona a invitar. Puede agregar varios emails, separados por espacios o comas"} y gana tickets para {link href="RIFA" caption="nuestra rifa"}.</small></p>
</center>
