const assert = require('node:assert/strict');
const path = require('node:path');
const { chromium } = require(process.env.PLAYWRIGHT_MODULE || 'playwright');
(async () => {
  const browser = await chromium.launch({headless:true});
  try {
    const page = await browser.newPage();
    await page.setContent('<main class="gadsan-module"><input id="cpf" data-gadsan-mask="cpf" value="01234567890"><input id="phone" data-gadsan-mask="phone" value="92991735192"><input id="email" data-gadsan-email><input id="rg" value="0012345-X"><input id="matricula" value="00042/A"><button id="blur">Sair do campo</button></main>');
    await page.addScriptTag({path:path.resolve(__dirname,'../../public/res/admin/gadsan/masks.js')});
    const cpf = page.locator('#cpf'), phone=page.locator('#phone');
    let checks=0;
    const eq=(actual,expected)=>{assert.equal(actual,expected);checks++;};
    eq(await cpf.inputValue(),'012.345.678-90');
    eq(await phone.inputValue(),'(92) 99173-5192');
    await cpf.fill('');await cpf.pressSequentially('52998224725');eq(await cpf.inputValue(),'529.982.247-25');
    await cpf.evaluate(input=>input.setSelectionRange(4,4));await cpf.press('Backspace');
    eq((await cpf.inputValue()).replace(/\D/g,''),'5298224725');
    eq(await cpf.evaluate(input=>input.selectionStart),2);
    await cpf.fill('52998224725');await cpf.evaluate(input=>input.setSelectionRange(3,3));await cpf.press('Delete');
    eq((await cpf.inputValue()).replace(/\D/g,''),'5298224725');
    await cpf.fill('52998224725');await cpf.evaluate(input=>input.setSelectionRange(4,7));await cpf.pressSequentially('123');
    eq(await cpf.inputValue(),'529.123.247-25');
    await cpf.fill('012345678901234');eq(await cpf.inputValue(),'012345678901234');
    for (const [raw,formatted] of [
      ['984518200','98451-8200'],['34518200','3451-8200'],['9234518200','(92) 3451-8200'],
      ['92991735192','(92) 99173-5192'],['5592991735192','+55 (92) 99173-5192'],
      ['+55 (92) 3451-8200','+55 (92) 3451-8200'],['123456789012345','123456789012345'],
      ['+1 202 555 0123','+1 202 555 0123']
    ]) {await phone.fill(raw);eq(await phone.inputValue(),formatted);eq((await phone.inputValue()).replace(/\D/g,''),raw.replace(/\D/g,''));}
    await phone.fill('');await phone.pressSequentially('+5592991735192');eq(await phone.inputValue(),'+55 (92) 99173-5192');
    await phone.fill('(92) 99173-5192');await phone.evaluate(input=>input.setSelectionRange(11,11));await phone.press('Backspace');
    eq((await phone.inputValue()).replace(/\D/g,''),'9299175192');
    await phone.fill('');eq(await phone.inputValue(),'');
    await page.locator('#email').fill('  Pessoa@EXAMPLE.COM  ');await page.locator('#blur').click();eq(await page.locator('#email').inputValue(),'pessoa@example.com');
    eq(await page.locator('#rg').inputValue(),'0012345-X');eq(await page.locator('#matricula').inputValue(),'00042/A');
    console.log(`OK: ${checks} verificações de máscaras, edição e preservação de dados no navegador. Nenhum acesso ao banco.`);
  } finally {await browser.close();}
})().catch(e=>{console.error(e.message);process.exitCode=1;});
