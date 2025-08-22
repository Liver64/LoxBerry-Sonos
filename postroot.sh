##!/bin/sh
# Will be executed as user "root".

INST=false
piper="/usr/local/bin/piper/piper"

if [ ! -e $piper ]; then
	if [ -e $LBSCONFIG/is_raspberry.cfg ]; then
		echo "<INFO> The hardware architecture is RaspBerry"
		wget -P /usr/local/bin https://github.com/rhasspy/piper/releases/download/2023.11.14-2/piper_linux_aarch64.tar.gz
		cd /usr/local/bin
		tar -xvzf piper_linux_aarch64.tar.gz
		INST=true
		rm piper_linux_aarch64.tar.gz
	fi

	if [ -e $LBSCONFIG/is_x86.cfg ]; then
		echo "<INFO> The hardware architecture is x86"
		wget -P /usr/local/bin https://github.com/rhasspy/piper/releases/download/2023.11.14-2/piper_linux_x86_64.tar.gz
		cd /usr/local/bin
		tar -xvzf piper_linux_x86_64.tar.gz
		rm piper_linux_x86_64.tar.gz
	fi

	if [ -e $LBSCONFIG/is_x64.cfg ]; then
		echo "<INFO> The hardware architecture is x64"
		if [ "$INST" != true ]; then
			wget -P /usr/local/bin https://github.com/rhasspy/piper/releases/download/2023.11.14-2/piper_linux_aarch64.tar.gz
			cd /usr/local/bin
			tar -xvzf piper_linux_aarch64.tar.gz
			rm piper_linux_aarch64.tar.gz
		else
			echo "<INFO> Piper TTS has already been installed upfront"
		fi
	fi
else
	echo "<INFO> Piper TTS is already installed, nothing to do..."
	echo "<INFO> Symlink 'piper' is already available in /usr/bin"
fi

sym="/usr/bin/piper"
if [ ! -L /usr/bin/piper ]; then
	chmod +x /usr/local/bin/piper/piper
	export PATH=/usr/local/bin/piper:$PATH
	ln -s /usr/local/bin/piper/piper /usr/bin/piper
	echo "<INFO> Symlink 'piper' has been created in /usr/bin"
fi

exit 0