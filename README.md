# astdocgen

`astdocgen` generates HTML documentation from an installed version of Asterisk. Nothing more, nothing less.

We already have the Asterisk Wiki, you may ask. Indeed, that's what inspired this project: https://wiki.asterisk.org/wiki/display/AST/Asterisk+18+Command+Reference

However, the Asterisk Wiki only documents things that *are in the official Asterisk source tree*. That's great if all of your modules are in the tree, but it doesn't help you if they aren't. Wouldn't it be great if you get that same great documentation for *any* module?

Now, you can easily generate wiki-style HTML documentation for your own custom modules.

## Supported Platforms

- Linux/BSD. We primarily test on Debian 10 and 11.
- Requires a modern version of PHP (>= 7.3)

## Example

The PhreakNet Asterisk documentation is generated using `astdocgen`:

https://asterisk.phreaknet.org

Your resulting webpage may be different if your Asterisk source contains different modules. This example uses PhreakNet Asterisk (installed by PhreakScript).

## Usage

### Automated Usage (recommended)

Use [PhreakScript](https://github.com/InterLinked1/phreakscript) - simply run `phreaknet docgen`. It will automatically install any pre-requisites and generate documentation using the latest version of `astdocgen`.

### Manual Usage

Run the following commands from the Asterisk source directory:

1. Install a supported version of PHP if you don't have one already. `astdocgen` is not tested on PHP < 7.3.

2. Generate array var dump of XML documentation (this step takes a minute):
`./astdocgen.php -f doc/core-en_US.xml -x -s > /tmp/astdocgen.xml`

3. Generate HTML documentation from the XML dump:
`./astdocgen.php -f /tmp/astdocgen.xml -h > doc/index.html`

4. Remove the temporary file:
`rm /tmp/astdocgen.xml`

Or, if you don't care about what each step is doing, just use this shell one-liner:

`./astdocgen.php -f doc/core-en_US.xml -x -s > /tmp/astdocgen.xml && ./astdocgen.php -f /tmp/astdocgen.xml -h > doc/index.html && rm /tmp/astdocgen.xml`

The result is a static HTML webpage with in-page CSS documenting all the applications, functions, and AMI actions in your installed version of Asterisk.

## Limitations

One thing to be aware of is the generated HTML contains all the CSS needed, but currently this does not come with the logo or font resources (heh, maybe there are good legal reasons for this...). You can find the CSS resources on your own by poking around the Asterisk Wiki. Put the fonts and Asterisk logo in the `fonts` folder (and a favicon in the root folder if you wish), and you're good to go.

Currently, `astdocgen` supports:
- Applications
- Functions
- AMI Actions
- AGI Commands
- Modules

Currently, `astdocgen` does not support:
- xpointer, i.e. references to other XML files for documentation generation. As a result, some documentation, especially for modules, is sparser than in the Wiki.
- AMI manager events, because the documentation for these solely consists of xpointers.
- Config files, because the documentation for these solely consists of xpointers.
- Info, which is mainly tech-specific `CHANNEL` function documentation.

## Bug Reporting

It is very likely that there are some bugs in this software. **THIS SOFTWARE COMES WITH NO WARRANTY OR GUARANTEES.** If you notice an inconsistency between the way documentation is generated on the Asterisk wiki versus this tool, please report it. Pull requests are also welcome.

The preferred issue reporting procedure is by cutting us a ticket at InterLinked Issues: https://issues.interlinked.us/

Choose "PhreakScript" as the category, and start the subject with "astdocgen".

