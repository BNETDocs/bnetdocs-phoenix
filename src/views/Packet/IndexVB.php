<?php

namespace BNETDocs\Views\Packet;

use \BNETDocs\Models\Packet\Index as PacketIndexModel;
use \CarlBennett\MVC\Libraries\Common;
use \CarlBennett\MVC\Libraries\Exceptions\IncorrectModelException;
use \CarlBennett\MVC\Libraries\Model;
use \CarlBennett\MVC\Libraries\View;

class IndexVB extends View {
  public function getMimeType() {
    return 'text/x-vb;charset=utf-8';
  }

  public function render(Model &$model) {
    if (!$model instanceof PacketIndexModel) {
      throw new IncorrectModelException();
    }

    echo "'\n";
    echo "'  BNETDocs, the documentation and discussion website for Blizzard protocols\n";
    echo "'  Copyright (C) 2003-2020  \"Arta\", Don Cullen \"Kyro\", Carl Bennett, others\n";
    echo "'  <" . Common::relativeUrlToAbsolute('/legal') . ">\n";
    echo "'\n";
    echo "'  BNETDocs is free software: you can redistribute it and/or modify\n";
    echo "'  it under the terms of the GNU Affero General Public License as published by\n";
    echo "'  the Free Software Foundation, either version 3 of the License, or\n";
    echo "'  (at your option) any later version.\n";
    echo "'\n";
    echo "'  BNETDocs is distributed in the hope that it will be useful,\n";
    echo "'  but WITHOUT ANY WARRANTY; without even the implied warranty of\n";
    echo "'  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the\n";
    echo "'  GNU Affero General Public License for more details.\n";
    echo "'\n";
    echo "'  You should have received a copy of the GNU Affero General Public License\n";
    echo "'  along with BNETDocs.  If not, see <http://www.gnu.org/licenses/>.\n";
    echo "'\n";

    echo "'  Packet ID constants for Visual Basic 6\n";
    echo "'  Generated by BNETDocs on " . $model->timestamp->format('r') . "\n";
    echo "'\n";

    echo "\n";

    foreach ($model->packets as $pkt) {
      echo 'CONST ' . $pkt->getPacketName() . '& = &H'
        . substr('0' . strtoupper(dechex($pkt->getPacketId())), -2) . "\n";
    }

    $model->_responseHeaders['Content-Type'] = $this->getMimeType();
  }
}
