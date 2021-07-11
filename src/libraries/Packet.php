<?php

namespace BNETDocs\Libraries;

use \BNETDocs\Libraries\Exceptions\PacketNotFoundException;
use \BNETDocs\Libraries\Exceptions\QueryException;
use \BNETDocs\Libraries\Packet\Application as ApplicationLayer;
use \BNETDocs\Libraries\Packet\Transport as TransportLayer;
use \BNETDocs\Libraries\User;
use \CarlBennett\MVC\Libraries\Common;
use \CarlBennett\MVC\Libraries\DatabaseDriver;
use \DateTime;
use \DateTimeZone;
use \InvalidArgumentException;
use \JsonSerializable;
use \OutOfBoundsException;
use \PDO;
use \PDOException;
use \Parsedown;
use \StdClass;
use \UnexpectedValueException;

class Packet implements JsonSerializable {

  const DATE_SQL = 'Y-m-d H:i:s';

  const DIRECTION_CLIENT_SERVER = 1;
  const DIRECTION_SERVER_CLIENT = 2;
  const DIRECTION_PEER_TO_PEER  = 3;

  const MAX_EDITED_COUNT = 0xFFFFFFFFFFFFFFFF;
  const MAX_PACKET_FORMAT = 0xFFFF;
  const MAX_PACKET_ID = 0xFF;
  const MAX_PACKET_NAME = 191;
  const MAX_PACKET_REMARKS = 0xFFFF;

  const OPTION_MARKDOWN   = 0x00000001;
  const OPTION_PUBLISHED  = 0x00000002;
  const OPTION_DEPRECATED = 0x00000004;
  const OPTION_RESEARCH   = 0x00000008;

  protected $created_datetime;
  protected $edited_count;
  protected $edited_datetime;
  protected $id;
  protected $options_bitmask;
  protected $packet_application_layer_id;
  protected $packet_direction_id;
  protected $packet_format;
  protected $packet_id;
  protected $packet_name;
  protected $packet_remarks;
  protected $packet_transport_layer_id;
  protected $user_id;

  public function __construct( $data ) {
    if ( is_numeric( $data )) {

      $this->created_datetime            = null;
      $this->edited_count                = null;
      $this->edited_datetime             = null;
      $this->id                          = (int) $data;
      $this->options_bitmask             = null;
      $this->packet_application_layer_id = null;
      $this->packet_direction_id         = null;
      $this->packet_format               = null;
      $this->packet_id                   = null;
      $this->packet_name                 = null;
      $this->packet_remarks              = null;
      $this->packet_transport_layer_id   = null;
      $this->user_id                     = null;
      $this->refresh();

    } else if ( $data instanceof StdClass ) {

      self::normalize( $data );

      $this->created_datetime            = $data->created_datetime;
      $this->edited_count                = $data->edited_count;
      $this->edited_datetime             = $data->edited_datetime;
      $this->id                          = $data->id;
      $this->options_bitmask             = $data->options_bitmask;
      $this->packet_application_layer_id = $data->packet_application_layer_id;
      $this->packet_direction_id         = $data->packet_direction_id;
      $this->packet_format               = $data->packet_format;
      $this->packet_id                   = $data->packet_id;
      $this->packet_name                 = $data->packet_name;
      $this->packet_remarks              = $data->packet_remarks;
      $this->packet_transport_layer_id   = $data->packet_transport_layer_id;
      $this->user_id                     = $data->user_id;

    } else {

      throw new InvalidArgumentException( 'Cannot use data argument' );

    }
  }

  public static function delete($id) {
    if (!isset(Common::$database)) {
      Common::$database = DatabaseDriver::getDatabaseObject();
    }
    $successful = false;
    try {
      $stmt = Common::$database->prepare("
        DELETE FROM `packets` WHERE `id` = :id LIMIT 1;
      ");
      $stmt->bindParam(":id", $id, PDO::PARAM_INT);
      $successful = $stmt->execute();
      $stmt->closeCursor();
    } catch (PDOException $e) {
      throw new QueryException("Cannot delete packet");
    } finally {
      return $successful;
    }
  }

  public static function &getAllPackets(
    $where_clause = null, $order = null, $limit = null, $index = null
  ) {

    if ( !empty( $where_clause )) {
      $where_clause = 'WHERE ' . $where_clause;
    }

    if ( !( is_numeric( $limit ) || is_numeric( $index ))) {
      $limit_clause = '';
    } else if ( !is_numeric( $index )) {
      $limit_clause = 'LIMIT ' . (int) $limit;
    } else {
      $limit_clause = 'LIMIT ' . (int) $index . ',' . (int) $limit;
    }

    $order_clause =
      ( $order ? '`' .
        implode( '`,`', explode( ',', $order[0] )) .
        '` ' . $order[1] . ',' : ''
      ) . '`id` ' . ( $order ? $order[1] : 'ASC' ) . ' ' .
      $limit_clause;

    if ( !isset( Common::$database )) {
      Common::$database = DatabaseDriver::getDatabaseObject();
    }

    try {

      $stmt = Common::$database->prepare('
        SELECT `created_datetime`,
               `edited_count`,
               `edited_datetime`,
               `id`,
               `options_bitmask`,
               `packet_application_layer_id`,
               `packet_direction_id`,
               `packet_format`,
               `packet_id`,
               `packet_name`,
               `packet_remarks`,
               `packet_transport_layer_id`,
               `user_id`
        FROM `packets` ' . $where_clause . '
        ORDER BY ' . $order_clause . ';
      ');

      if ( !$stmt->execute() ) {
        throw new QueryException( 'Cannot refresh all packets' );
      }

      $objects = [];
      while ( $row = $stmt->fetch( PDO::FETCH_OBJ )) {
        $objects[] = new self( $row );
      }

      $stmt->closeCursor();
      return $objects;

    } catch ( PDOException $e ) {
      throw new QueryException( 'Cannot refresh packets', $e );
    }

    return null;
  }

  public static function getPacketsByLastEdited(int $count)
  {
    if (!isset(Common::$database))
    {
      Common::$database = DatabaseDriver::getDatabaseObject();
    }

    $stmt = Common::$database->prepare('
      SELECT `created_datetime`,
             `edited_count`,
             `edited_datetime`,
             `id`,
             `options_bitmask`,
             `packet_application_layer_id`,
             `packet_direction_id`,
             `packet_format`,
             `packet_id`,
             `packet_name`,
             `packet_remarks`,
             `packet_transport_layer_id`,
             `user_id`
      FROM `packets`
      ORDER BY IFNULL(`edited_datetime`, `created_datetime`) DESC
      LIMIT ' . $count . '
    ');

    $r = $stmt->execute();
    if (!$r)
    {
      throw new QueryException('Cannot get packets by date');
      return $r;
    }

    $r = [];
    while ($row = $stmt->fetch(PDO::FETCH_OBJ))
    {
      $r[] = new self($row);
    }

    $stmt->closeCursor();
    return $r;
  }

  public static function getAllPacketsBySearch($query) {

    if ( !isset( Common::$database )) {
      Common::$database = DatabaseDriver::getDatabaseObject();
    }

    try {

      $stmt = Common::$database->prepare('
        SELECT
          `created_datetime`,
          `edited_count`,
          `edited_datetime`,
          `id`,
          `options_bitmask`,
          `packet_application_layer_id`,
          `packet_direction_id`,
          `packet_format`,
          `packet_id`,
          `packet_name`,
          `packet_remarks`,
          `packet_transport_layer_id`,
          `user_id`
        FROM `packets`
        WHERE
          MATCH (`packet_remarks`, `packet_format`, `packet_name`)
          AGAINST (:query IN NATURAL LANGUAGE MODE WITH QUERY EXPANSION)
        ;
      ');

      $stmt->bindParam( ':query', $query, PDO::PARAM_STR );

      if ( !$stmt->execute() ) {
        throw new QueryException( 'Cannot search packets' );
      }

      $objects = [];
      while ( $row = $stmt->fetch( PDO::FETCH_OBJ )) {
        $objects[] = new self( $row );
      }

      $stmt->closeCursor();
      return $objects;

    } catch ( PDOException $e ) {
      throw new QueryException( 'Cannot search packets', $e );
    }

    return null;
  }

  public function getCreatedDateTime() {
    if ( is_null( $this->created_datetime )) {
      return $this->created_datetime;
    }

    $tz = new DateTimeZone( 'Etc/UTC' );
    $dt = new DateTime( $this->created_datetime );

    $dt->setTimezone( $tz );

    return $dt;
  }

  public function getEditedCount() {
    return $this->edited_count;
  }

  public function getEditedDateTime() {
    if ( is_null( $this->edited_datetime )) {
      return $this->edited_datetime;
    }

    $tz = new DateTimeZone( 'Etc/UTC' );
    $dt = new DateTime( $this->edited_datetime );

    $dt->setTimezone( $tz );

    return $dt;
  }

  public function getId() {
    return $this->id;
  }

  public function getName() {
    return sprintf('%s %s %s',
      $this->getPacketDirectionTag(),
      $this->getPacketId(true),
      $this->getPacketName()
    );
  }

  public function getOptionsBitmask() {
    return $this->options_bitmask;
  }

  public function getPacketApplicationLayer() {
    return new ApplicationLayer( $this->packet_application_layer );
  }

  public function getPacketApplicationLayerId() {
    return $this->packet_application_layer_id;
  }

  public function getPacketDirectionId() {
    return $this->packet_direction_id;
  }

  public function getPacketDirectionLabel() {
    switch ($this->packet_direction_id) {
      case self::DIRECTION_CLIENT_SERVER: return 'Client to Server';
      case self::DIRECTION_SERVER_CLIENT: return 'Server to Client';
      case self::DIRECTION_PEER_TO_PEER:  return 'Peer to Peer';
      default:
        throw new UnexpectedValueException(sprintf(
          'packet direction: %d is invalid', $this->packet_direction_id
        ));
    }
  }

  public function getPacketDirectionTag() {
    switch ($this->packet_direction_id) {
      case self::DIRECTION_CLIENT_SERVER: return 'C>S';
      case self::DIRECTION_SERVER_CLIENT: return 'S>C';
      case self::DIRECTION_PEER_TO_PEER:  return 'P2P';
      default:
        throw new UnexpectedValueException(sprintf(
          'packet direction: %d is invalid', $this->packet_direction_id
        ));
    }
  }

  public function getPacketFormat() {
    return $this->packet_format;
  }

  public function getPacketName() {
    return $this->packet_name;
  }

  public function getPacketId( $format = false ) {
    if (!$format) {
      return $this->packet_id;
    }

    return '0x' . strtoupper( substr( '0' . dechex( $this->packet_id ), -2 ));
  }

  public function getPacketRemarks( $prepare ) {
    if ( !$prepare ) {
      return $this->packet_remarks;
    }

    if ( $this->options_bitmask & self::OPTION_MARKDOWN ) {
      $md = new Parsedown();
      return $md->text($this->packet_remarks);
    }

    return $this->packet_remarks;
  }

  public function getPacketTransportLayer() {
    return new TransportLayer( $this->packet_transport_layer_id );
  }

  public function getPacketTransportLayerId() {
    return $this->packet_transport_layer_id;
  }

  public static function getPacketsByUserId( $user_id ) {
    if ( !isset( Common::$database )) {
      Common::$database = DatabaseDriver::getDatabaseObject();
    }

    try {

      $stmt = Common::$database->prepare('
        SELECT
          `created_datetime`,
          `edited_count`,
          `edited_datetime`,
          `id`,
          `options_bitmask`,
          `packet_application_layer_id`,
          `packet_direction_id`,
          `packet_format`,
          `packet_id`,
          `packet_name`,
          `packet_remarks`,
          `packet_transport_layer_id`,
          `user_id`
        FROM `packets`
        WHERE `user_id` = :user_id
        ORDER BY `id` ASC;
      ');

      $stmt->bindParam( ':user_id', $user_id, PDO::PARAM_INT );

      if ( !$stmt->execute() ) {
        throw new QueryException( 'Cannot query packets by user id' );
      }

      $packets = [];
      while ( $row = $stmt->fetch( PDO::FETCH_OBJ )) {
        $packets[] = new self( $row );
      }

      $stmt->closeCursor();
      return $packets;

    } catch ( PDOException $e ) {
      throw new QueryException( 'Cannot query packets by user id', $e );
    }

    return null;
  }

  public static function getPacketCount() {
    if (!isset(Common::$database)) {
      Common::$database = DatabaseDriver::getDatabaseObject();
    }

    try {

      $stmt = Common::$database->prepare('SELECT COUNT(*) FROM `packets`;');

      if ( !$stmt->execute() ) {
        throw new QueryException( 'Cannot query packet count' );
      } else if ( $stmt->rowCount() == 0 ) {
        throw new QueryException(
          'Missing result while querying packet count'
        );
      }

      $row = $stmt->fetch( PDO::FETCH_NUM );

      $stmt->closeCursor();

      return (int) $row[0];

    } catch ( PDOException $e ) {

      throw new QueryException( 'Cannot query packet count', $e );

    }

    return null;
  }

  public function getPublishedDateTime() {
    if ( !is_null( $this->edited_datetime )) {
      return $this->getEditedDateTime();
    }

    return $this->getCreatedDateTime();
  }

  public function getURI() {
    return Common::relativeUrlToAbsolute(
      '/packet/' . $this->getId() . '/' . Common::sanitizeForUrl(
        $this->getPacketName(), true
      )
    );
  }

  public function getUsedBy() {

    if ( !isset( Common::$database )) {
      Common::$database = DatabaseDriver::getDatabaseObject();
    }

    try {

      $stmt = Common::$database->prepare('
        SELECT
          `used`.`bnet_product_id`
        FROM `packet_used_by` AS `used`
        INNER JOIN
          `products` AS `prods`
        ON `used`.`bnet_product_id` = `prods`.`bnet_product_id`
        WHERE `used`.`id` = :id
        ORDER BY `prods`.`sort` ASC;
      ');

      $stmt->bindParam( ':id', $this->id, PDO::PARAM_INT );

      if (!$stmt->execute()) {
        throw new QueryException( 'Cannot query packet used by' );
      }

      $values = [];
      while ( $row = $stmt->fetch( PDO::FETCH_OBJ )) {
        $values[] = (int) $row->bnet_product_id;
      }

      $stmt->closeCursor();
      return $values;

    } catch ( PDOException $e ) {
      throw new QueryException( 'Cannot query packet used by', $e );
    }

    return null;
  }

  public function getUser() {
    if (is_null($this->user_id)) return null;
    return new User($this->user_id);
  }

  public function getUserId() {
    return $this->user_id;
  }

  public function isDeprecated() {
    return ($this->options_bitmask & self::OPTION_DEPRECATED);
  }

  public function isInResearch() {
    return ($this->options_bitmask & self::OPTION_RESEARCH);
  }

  public function isMarkdown() {
    return ($this->options_bitmask & self::OPTION_MARKDOWN);
  }

  public function isPublished() {
    return ($this->options_bitmask & self::OPTION_PUBLISHED);
  }

  public function jsonSerialize() {
    return [
      'created_datetime'            => $this->getCreatedDateTime(),
      'edited_count'                => $this->getEditedCount(),
      'edited_datetime'             => $this->getEditedDateTime(),
      'id'                          => $this->getId(),
      'options_bitmask'             => $this->getOptionsBitmask(),
      'packet_application_layer_id' => $this->getPacketApplicationLayerId(),
      'packet_direction_id'         => $this->getPacketDirectionId(),
      'packet_format'               => $this->getPacketFormat(),
      'packet_id'                   => $this->getPacketId(),
      'packet_name'                 => $this->getPacketName(),
      'packet_remarks'              => $this->getPacketRemarks( false ),
      'packet_transport_layer_id'   => $this->getPacketTransportLayerId(),
      'user'                        => $this->getUser(),
    ];
  }

  protected static function normalize( StdClass &$data ) {

    $data->created_datetime            = (string) $data->created_datetime;
    $data->edited_count                = (int)    $data->edited_count;
    $data->id                          = (int)    $data->id;
    $data->options_bitmask             = (int)    $data->options_bitmask;
    $data->packet_application_layer_id = (int)    $data->packet_application_layer_id;
    $data->packet_direction_id         = (int)    $data->packet_direction_id;
    $data->packet_format               = (string) $data->packet_format;
    $data->packet_id                   = (int)    $data->packet_id;
    $data->packet_name                 = (string) $data->packet_name;
    $data->packet_remarks              = (string) $data->packet_remarks;
    $data->packet_transport_layer_id   = (int)    $data->packet_transport_layer_id;

    if (!is_null($data->edited_datetime))
      $data->edited_datetime = $data->edited_datetime;

    if (!is_null($data->user_id))
      $data->user_id = (int) $data->user_id;

    return true;
  }

  public function refresh() {

    if ( !isset( Common::$database )) {
      Common::$database = DatabaseDriver::getDatabaseObject();
    }

    try {

      $stmt = Common::$database->prepare('
        SELECT
          `created_datetime`,
          `edited_count`,
          `edited_datetime`,
          `id`,
          `options_bitmask`,
          `packet_application_layer_id`,
          `packet_direction_id`,
          `packet_format`,
          `packet_id`,
          `packet_name`,
          `packet_remarks`,
          `packet_transport_layer_id`,
          `user_id`
        FROM `packets`
        WHERE `id` = :id
        LIMIT 1;
      ');

      $stmt->bindParam( ':id', $this->id, PDO::PARAM_INT );

      if ( !$stmt->execute() ) {
        throw new QueryException( 'Cannot refresh packet' );
      } else if ( $stmt->rowCount() == 0 ) {
        throw new PacketNotFoundException( $this->id );
      }

      $row = $stmt->fetch( PDO::FETCH_OBJ );

      $stmt->closeCursor();

      self::normalize( $row );

      $this->created_datetime            = $row->created_datetime;
      $this->edited_count                = $row->edited_count;
      $this->edited_datetime             = $row->edited_datetime;
      $this->id                          = $row->id;
      $this->options_bitmask             = $row->options_bitmask;
      $this->packet_application_layer_id = $row->packet_application_layer_id;
      $this->packet_direction_id         = $row->packet_direction_id;
      $this->packet_format               = $row->packet_format;
      $this->packet_id                   = $row->packet_id;
      $this->packet_name                 = $row->packet_name;
      $this->packet_remarks              = $row->packet_remarks;
      $this->packet_transport_layer_id   = $row->packet_transport_layer_id;
      $this->user_id                     = $row->user_id;

      return true;

    } catch ( PDOException $e ) {

      throw new QueryException( 'Cannot refresh packet', $e );

    }

    return false;
  }

  public function setDeprecated( $value ) {
    if ( $value ) {
      $this->options_bitmask |= self::OPTION_DEPRECATED;
    } else {
      $this->options_bitmask &= ~self::OPTION_DEPRECATED;
    }
  }

  public function setEditedCount(int $value)
  {
    if ($value < 0 || $value > self::MAX_EDITED_COUNT)
    {
      throw new OutOfBoundsException();
    }

    $this->edited_count = $value;
  }

  public function setEditedDateTime(DateTime $value)
  {
    $this->edited_datetime = $value->format(self::DATE_SQL);
  }

  public function setInResearch( $value ) {
    if ( $value ) {
      $this->options_bitmask |= self::OPTION_RESEARCH;
    } else {
      $this->options_bitmask &= ~self::OPTION_RESEARCH;
    }
  }

  public function setMarkdown( $value ) {
    if ( $value ) {
      $this->options_bitmask |= self::OPTION_MARKDOWN;
    } else {
      $this->options_bitmask &= ~self::OPTION_MARKDOWN;
    }
  }

  public function setPacketFormat(string $value)
  {
    if (strlen($value) > self::MAX_PACKET_FORMAT)
    {
      throw new OutOfBoundsException();
    }

    $this->packet_format = $value;
  }

  /**
   * Sets the packet/message id
   *
   * @param mixed $value The packet id. Supports hexadecimal and octal input.
   */
  public function setPacketId($value)
  {
    if (is_string($value) && strlen($value) >= 2
      && substr($value, 0, 2) == '0x')
    {
      $this->packet_id = hexdec(substr($value, 2));
    }
    else if (is_string($value) && strlen($value) >= 1
      && substr($value, 0, 1) == '0')
    {
      $this->packet_id = octdec(substr($value, 1));
    }
    else if (is_numeric($value))
    {
      $this->packet_id = (int) $value;
    }
    else
    {
      throw new InvalidArgumentException();
    }

    if ($this->packet_id < 0 || $this->packet_id > self::MAX_PACKET_ID)
    {
      throw new OutOfBoundsException();
    }
  }

  public function setPacketName(string $value)
  {
    if (strlen($value) > self::MAX_PACKET_NAME)
    {
      throw new OutOfBoundsException();
    }

    $this->packet_name = $value;
  }

  public function setPacketRemarks(string $value)
  {
    if (strlen($value) > self::MAX_PACKET_REMARKS)
    {
      throw new OutOfBoundsException();
    }

    $this->packet_remarks = $value;
  }

  public function setPublished( $value ) {
    if ( $value ) {
      $this->options_bitmask |= self::OPTION_PUBLISHED;
    } else {
      $this->options_bitmask &= ~self::OPTION_PUBLISHED;
    }
  }

  public function setUsedBy( $value ) {
    if (!isset(Common::$database)) {
      Common::$database = DatabaseDriver::getDatabaseObject();
    }
    try {
      Common::$database->beginTransaction();
      $stmt = Common::$database->prepare('
        DELETE FROM `packet_used_by`
        WHERE `id` = :id;
      ');
      $stmt->bindParam(':id', $this->id, PDO::PARAM_INT);
      if (!$stmt->execute()) {
        throw new QueryException('Cannot update packet used by');
      }
      if ($value !== null && count($value) > 0) {
        $insert = [];
        $placeholders = [];
        foreach ($value as $v) {
          array_push($insert, $this->id, (int)$v);
          $placeholders[] = '(?, ?)';
        }
        $stmt = Common::$database->prepare('
          INSERT INTO `packet_used_by`
            (`id`, `bnet_product_id`)
          VALUES
            ' . implode(', ', $placeholders) . '; 
        ');
        if (!$stmt->execute($insert)) {
          Common::$database->rollBack();
          throw new QueryException('Cannot update packet used by');
        }
      }

      Common::$database->commit();
    } catch (PDOException $e) {
      Common::$database->rollBack();
      throw new QueryException('Cannot update packet used by', $e);
    }
  }

  public function update() {
    if (!isset(Common::$database)) {
      Common::$database = DatabaseDriver::getDatabaseObject();
    }

    try {

      $stmt = Common::$database->prepare('
        UPDATE `packets` SET
          `created_datetime` = :dt1,
          `edited_count` = :edit_count,
          `edited_datetime` = :dt2,
          `options_bitmask` = :options,
          `packet_application_layer_id` = :app_layer_id,
          `packet_direction_id` = :direction,
          `packet_format` = :format,
          `packet_id` = :pid,
          `packet_name` = :name,
          `packet_remarks` = :remarks,
          `packet_transport_layer_id` = :transport_layer_id,
          `user_id` = :user_id
        WHERE
          `id` = :id
        LIMIT 1;
      ');

      $stmt->bindParam(
        ':app_layer_id', $this->packet_application_layer_id, PDO::PARAM_INT
      );

      $stmt->bindParam(':dt1', $this->created_datetime, PDO::PARAM_STR);
      $stmt->bindParam(':edit_count', $this->edited_count, PDO::PARAM_INT);

      $stmt->bindParam(':dt2', $this->edited_datetime, (
        is_null($this->edited_datetime) ? PDO::PARAM_NULL : PDO::PARAM_STR
      ));

      $stmt->bindParam(
        ':direction', $this->packet_direction_id, PDO::PARAM_INT
      );

      $stmt->bindParam( ':format', $this->packet_format, PDO::PARAM_STR );
      $stmt->bindParam( ':id', $this->id, PDO::PARAM_INT );
      $stmt->bindParam( ':name', $this->packet_name, PDO::PARAM_STR );
      $stmt->bindParam( ':options', $this->options_bitmask, PDO::PARAM_INT );
      $stmt->bindParam( ':pid', $this->packet_id, PDO::PARAM_INT );
      $stmt->bindParam( ':remarks', $this->packet_remarks, PDO::PARAM_STR );

      $stmt->bindParam(
        ':transport_layer_id', $this->packet_transport_layer_id, PDO::PARAM_INT
      );

      if ( is_null( $this->user_id )) {
        $stmt->bindParam( ':user_id', $this->user_id, PDO::PARAM_NULL );
      } else {
        $stmt->bindParam( ':user_id', $this->user_id, PDO::PARAM_INT );
      }

      if ( !$stmt->execute() ) {
        throw new QueryException( 'Cannot update packet' );
      }

      $stmt->closeCursor();
      return true;

    } catch ( PDOException $e ) {
      throw new QueryException( 'Cannot update packet', $e );
    }
    return false;
  }

}
